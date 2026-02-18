<?php
/**
 * Invoice Matching Service
 * Smart matching algorithm for reconciling system invoices with Xero invoices
 */

namespace App\Services;

use App\Database;
use Exception;

class InvoiceMatchingService {
    private $db;
    
    // Matching thresholds
    const PERFECT_MATCH_THRESHOLD = 100;
    const AUTO_MATCH_THRESHOLD = 95;
    const SUGGESTED_MATCH_THRESHOLD = 70;
    
    // Score weights
    const ENTITY_EXACT_SCORE = 40;
    const ENTITY_HIGH_SCORE = 35;
    const ENTITY_MEDIUM_SCORE = 25;
    const DATE_EXACT_SCORE = 30;
    const DATE_CLOSE_SCORE = 25;
    const DATE_MEDIUM_SCORE = 15;
    const AMOUNT_EXACT_SCORE = 30;
    const AMOUNT_CLOSE_SCORE = 25;
    const AMOUNT_MEDIUM_SCORE = 20;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Calculate similarity between two strings (0-100%)
     * Uses Levenshtein distance and similar_text
     */
    private function calculateStringSimilarity($str1, $str2) {
        // Normalize strings
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        // Remove common business suffixes
        $suffixes = [' ltd', ' limited', ' plc', ' inc', ' llc', ' &', '&'];
        foreach ($suffixes as $suffix) {
            $str1 = str_replace($suffix, '', $str1);
            $str2 = str_replace($suffix, '', $str2);
        }
        
        // Exact match
        if ($str1 === $str2) {
            return 100;
        }
        
        // Calculate similarity percentage
        similar_text($str1, $str2, $percent);
        
        return round($percent, 2);
    }
    
    /**
     * Calculate date difference in days
     */
    private function calculateDateDifference($date1, $date2) {
        $d1 = new \DateTime($date1);
        $d2 = new \DateTime($date2);
        return abs($d1->diff($d2)->days);
    }
    
    /**
     * Calculate amount difference percentage
     */
    private function calculateAmountDifference($amount1, $amount2) {
        if ($amount1 == 0 || $amount2 == 0) {
            return 100;
        }
        
        $diff = abs($amount1 - $amount2);
        $avg = ($amount1 + $amount2) / 2;
        
        return round(($diff / $avg) * 100, 2);
    }
    
    /**
     * Calculate match score between system invoice and Xero invoice
     * Returns array with score and breakdown
     */
    public function calculateMatchScore($systemInvoice, $xeroInvoice) {
        $score = 0;
        $breakdown = [];

        // 1. Entity Name Matching (max 40 points)
        // Use xero_company_name from legal_entities table to match with Xero's contact_name
        $entitySimilarity = $this->calculateStringSimilarity(
            $systemInvoice['xero_company_name'] ?? $systemInvoice['legal_entity_name'],
            $xeroInvoice['contact_name']
        );
        
        if ($entitySimilarity >= 100) {
            $score += self::ENTITY_EXACT_SCORE;
            $breakdown['entity'] = ['score' => self::ENTITY_EXACT_SCORE, 'similarity' => 100, 'match' => 'exact'];
        } elseif ($entitySimilarity >= 90) {
            $score += self::ENTITY_HIGH_SCORE;
            $breakdown['entity'] = ['score' => self::ENTITY_HIGH_SCORE, 'similarity' => $entitySimilarity, 'match' => 'high'];
        } elseif ($entitySimilarity >= 75) {
            $score += self::ENTITY_MEDIUM_SCORE;
            $breakdown['entity'] = ['score' => self::ENTITY_MEDIUM_SCORE, 'similarity' => $entitySimilarity, 'match' => 'medium'];
        } else {
            $breakdown['entity'] = ['score' => 0, 'similarity' => $entitySimilarity, 'match' => 'low'];
        }
        
        // 2. Date Matching (max 30 points)
        $dateDiff = $this->calculateDateDifference(
            $systemInvoice['invoice_date'],
            $xeroInvoice['invoice_date']
        );
        
        if ($dateDiff === 0) {
            $score += self::DATE_EXACT_SCORE;
            $breakdown['date'] = ['score' => self::DATE_EXACT_SCORE, 'diff_days' => 0, 'match' => 'exact'];
        } elseif ($dateDiff <= 3) {
            $score += self::DATE_CLOSE_SCORE;
            $breakdown['date'] = ['score' => self::DATE_CLOSE_SCORE, 'diff_days' => $dateDiff, 'match' => 'close'];
        } elseif ($dateDiff <= 7) {
            $score += self::DATE_MEDIUM_SCORE;
            $breakdown['date'] = ['score' => self::DATE_MEDIUM_SCORE, 'diff_days' => $dateDiff, 'match' => 'medium'];
        } else {
            $breakdown['date'] = ['score' => 0, 'diff_days' => $dateDiff, 'match' => 'poor'];
        }
        
        // 3. Amount Matching (max 30 points)
        $amountDiff = $this->calculateAmountDifference(
            $systemInvoice['invoice_amount'],
            $xeroInvoice['amount']
        );
        
        if ($amountDiff === 0.0) {
            $score += self::AMOUNT_EXACT_SCORE;
            $breakdown['amount'] = ['score' => self::AMOUNT_EXACT_SCORE, 'diff_percent' => 0, 'match' => 'exact'];
        } elseif ($amountDiff <= 2) {
            $score += self::AMOUNT_CLOSE_SCORE;
            $breakdown['amount'] = ['score' => self::AMOUNT_CLOSE_SCORE, 'diff_percent' => $amountDiff, 'match' => 'close'];
        } elseif ($amountDiff <= 5) {
            $score += self::AMOUNT_MEDIUM_SCORE;
            $breakdown['amount'] = ['score' => self::AMOUNT_MEDIUM_SCORE, 'diff_percent' => $amountDiff, 'match' => 'medium'];
        } else {
            $breakdown['amount'] = ['score' => 0, 'diff_percent' => $amountDiff, 'match' => 'poor'];
        }
        
        return [
            'total_score' => $score,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Find best matches for all Xero invoices in a session
     * Returns array of matches grouped by confidence level
     */
    /**
     * Get all matches for a session (including already matched/reconciled)
     * This is used when viewing historical sessions
     */
    public function getSessionMatches($sessionId) {
        // Get ALL Xero invoices from this session with their match info
        $xeroInvoices = $this->db->fetchAll("
            SELECT xii.*,
                   i.invoice_number as system_invoice_number,
                   i.invoice_date as system_invoice_date,
                   i.invoice_amount as system_amount,
                   i.invoice_status as system_status,
                   le.legal_entity_name,
                   le.xero_company_name
            FROM xero_imported_invoices xii
            LEFT JOIN invoices i ON xii.matched_invoice_id = i.id
            LEFT JOIN legal_entities le ON i.legal_entity_id = le.id
            WHERE xii.session_id = :session_id
            ORDER BY xii.match_status, xii.match_score DESC
        ", ['session_id' => $sessionId]);

        // DEBUG: Log what we retrieved
        error_log("getSessionMatches($sessionId): Retrieved " . count($xeroInvoices) . " Xero invoices");
        foreach ($xeroInvoices as $xi) {
            error_log("  - Xero #{$xi['xero_invoice_number']}: status={$xi['match_status']}, matched_id={$xi['matched_invoice_id']}, score={$xi['match_score']}, reconciled=" . ($xi['is_reconciled'] ? 'YES' : 'NO'));
        }

        $matches = [
            'perfect' => [],
            'auto' => [],
            'suggested' => [],
            'possible' => [],
            'unmatched' => []
        ];

        foreach ($xeroInvoices as $xeroInvoice) {
            if ($xeroInvoice['match_status'] === 'unmatched') {
                $matches['unmatched'][] = ['xero_invoice' => $xeroInvoice];
                error_log("    → Added to UNMATCHED");
            } else {
                // Reconstruct the match from saved data
                $match = [
                    'xero_invoice' => $xeroInvoice,
                    'system_invoice' => [
                        'id' => $xeroInvoice['matched_invoice_id'],
                        'invoice_number' => $xeroInvoice['system_invoice_number'],
                        'invoice_date' => $xeroInvoice['system_invoice_date'],
                        'invoice_amount' => $xeroInvoice['system_amount'],
                        'invoice_status' => $xeroInvoice['system_status'],
                        'legal_entity_name' => $xeroInvoice['legal_entity_name'],
                        'xero_company_name' => $xeroInvoice['xero_company_name']
                    ],
                    'score' => $xeroInvoice['match_score'],
                    'breakdown' => json_decode($xeroInvoice['match_breakdown'] ?? '{}', true)
                ];

                $score = $xeroInvoice['match_score'];
                if ($score >= self::PERFECT_MATCH_THRESHOLD) {
                    $matches['perfect'][] = $match;
                    error_log("    → Added to PERFECT (score=$score)");
                } elseif ($score >= self::AUTO_MATCH_THRESHOLD) {
                    $matches['auto'][] = $match;
                    error_log("    → Added to AUTO (score=$score)");
                } elseif ($score >= self::SUGGESTED_MATCH_THRESHOLD) {
                    $matches['suggested'][] = $match;
                    error_log("    → Added to SUGGESTED (score=$score)");
                } else {
                    $matches['possible'][] = $match;
                    error_log("    → Added to POSSIBLE (score=$score)");
                }
            }
        }

        error_log("getSessionMatches($sessionId): Returning perfect=" . count($matches['perfect']) . ", auto=" . count($matches['auto']) . ", suggested=" . count($matches['suggested']) . ", possible=" . count($matches['possible']) . ", unmatched=" . count($matches['unmatched']));

        return $matches;
    }

    public function findMatches($sessionId) {
        // Get all unmatched Xero invoices from this session
        $xeroInvoices = $this->db->fetchAll("
            SELECT * FROM xero_imported_invoices
            WHERE session_id = :session_id
            AND match_status = 'unmatched'
        ", ['session_id' => $sessionId]);

        // Get all unreconciled system invoices with status 'issued'
        // Include xero_company_name for accurate matching
        $systemInvoices = $this->db->fetchAll("
            SELECT i.*, le.legal_entity_name, le.xero_company_name
            FROM invoices i
            JOIN legal_entities le ON i.legal_entity_id = le.id
            WHERE i.invoice_status = 'issued'
            AND i.id NOT IN (
                SELECT matched_invoice_id
                FROM xero_imported_invoices
                WHERE matched_invoice_id IS NOT NULL
                AND session_id = :session_id
            )
        ", ['session_id' => $sessionId]);

        $matches = [
            'perfect' => [],
            'auto' => [],
            'suggested' => [],
            'possible' => [],
            'unmatched' => []
        ];

        $usedSystemInvoices = [];

        foreach ($xeroInvoices as $xeroInvoice) {
            $bestMatch = null;
            $bestScore = 0;

            // Parse rejected IDs from JSON array
            $rejectedIds = [];
            if (!empty($xeroInvoice['manually_rejected_invoice_id'])) {
                $decoded = json_decode($xeroInvoice['manually_rejected_invoice_id'], true);
                if (is_array($decoded)) {
                    $rejectedIds = $decoded;
                }
            }

            error_log("  Xero #{$xeroInvoice['xero_invoice_number']}: manually_rejected_invoice_ids = " . json_encode($rejectedIds));

            foreach ($systemInvoices as $systemInvoice) {
                // Skip if already matched
                if (in_array($systemInvoice['id'], $usedSystemInvoices)) {
                    continue;
                }

                // Skip if this specific pairing was manually rejected by the user
                if (in_array($systemInvoice['id'], $rejectedIds)) {
                    error_log("    → Skipping system invoice #{$systemInvoice['id']} (manually rejected)");
                    continue;
                }

                $matchResult = $this->calculateMatchScore($systemInvoice, $xeroInvoice);

                if ($matchResult['total_score'] > $bestScore) {
                    $bestScore = $matchResult['total_score'];
                    $bestMatch = [
                        'xero_invoice' => $xeroInvoice,
                        'system_invoice' => $systemInvoice,
                        'score' => $matchResult['total_score'],
                        'breakdown' => $matchResult['breakdown']
                    ];
                }
            }

            if ($bestMatch) {
                if ($bestScore >= self::PERFECT_MATCH_THRESHOLD) {
                    error_log("    → MATCHED with system invoice #{$bestMatch['system_invoice']['id']} (score={$bestScore})");
                    $matches['perfect'][] = $bestMatch;
                    $usedSystemInvoices[] = $bestMatch['system_invoice']['id'];
                } elseif ($bestScore >= self::AUTO_MATCH_THRESHOLD) {
                    $matches['auto'][] = $bestMatch;
                    $usedSystemInvoices[] = $bestMatch['system_invoice']['id'];
                } elseif ($bestScore >= self::SUGGESTED_MATCH_THRESHOLD) {
                    $matches['suggested'][] = $bestMatch;
                } else {
                    $matches['possible'][] = $bestMatch;
                }
            } else {
                error_log("    → NO MATCH FOUND (all candidates rejected or no matches)");
                $matches['unmatched'][] = ['xero_invoice' => $xeroInvoice];
            }
        }

        return $matches;
    }

    /**
     * Accept a match and update the database
     */
    public function acceptMatch($xeroInvoiceId, $systemInvoiceId, $score, $breakdown, $userId) {
        $this->db->beginTransaction();

        try {
            // Update xero_imported_invoices
            $this->db->update('xero_imported_invoices', [
                'matched_invoice_id' => $systemInvoiceId,
                'match_confidence' => $score,
                'match_score' => $score,
                'match_breakdown' => json_encode($breakdown),
                'match_status' => $score >= self::AUTO_MATCH_THRESHOLD ? 'auto_matched' : 'manual_matched',
                'match_reason' => json_encode($breakdown),
                'manually_rejected_invoice_id' => null  // Clear any previous rejection
            ], 'id = :id', ['id' => $xeroInvoiceId]);

            // Log the match
            $xeroInvoice = $this->db->fetchOne("SELECT * FROM xero_imported_invoices WHERE id = :id", ['id' => $xeroInvoiceId]);

            $this->db->insert('matching_audit_log', [
                'session_id' => $xeroInvoice['session_id'],
                'xero_invoice_id' => $xeroInvoiceId,
                'system_invoice_id' => $systemInvoiceId,
                'match_score' => $score,
                'match_details' => json_encode($breakdown),
                'action' => $score >= self::AUTO_MATCH_THRESHOLD ? 'auto_matched' : 'accepted',
                'performed_by' => $userId
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reject a match
     */
    public function rejectMatch($xeroInvoiceId, $systemInvoiceId, $userId) {
        // Get the current match info before rejecting
        $xeroInvoice = $this->db->fetchOne("SELECT * FROM xero_imported_invoices WHERE id = :id", ['id' => $xeroInvoiceId]);

        // Get existing rejected IDs (stored as JSON array)
        $rejectedIds = [];
        if (!empty($xeroInvoice['manually_rejected_invoice_id'])) {
            $decoded = json_decode($xeroInvoice['manually_rejected_invoice_id'], true);
            if (is_array($decoded)) {
                $rejectedIds = $decoded;
            }
        }

        // Add the new rejected ID to the array
        if ($systemInvoiceId && !in_array($systemInvoiceId, $rejectedIds)) {
            $rejectedIds[] = (int)$systemInvoiceId;
        }

        $this->db->update('xero_imported_invoices', [
            'match_status' => 'unmatched',  // Changed from 'rejected' to 'unmatched' so it appears in unmatched list
            'matched_invoice_id' => null,
            'match_confidence' => null,
            'match_reason' => null,  // Reset match reason as well
            'manually_rejected_invoice_id' => json_encode($rejectedIds)  // Store as JSON array
        ], 'id = :id', ['id' => $xeroInvoiceId]);

        // Log the rejection
        $this->db->insert('matching_audit_log', [
            'session_id' => $xeroInvoice['session_id'],
            'xero_invoice_id' => $xeroInvoiceId,
            'system_invoice_id' => $systemInvoiceId,
            'match_score' => 0,
            'match_details' => json_encode(['reason' => 'rejected by user']),
            'action' => 'rejected',
            'performed_by' => $userId
        ]);
    }

    /**
     * Delete a Xero invoice from the import session
     *
     * @param int $xeroInvoiceId The Xero invoice ID to delete
     * @param int $sessionId The session ID
     * @param int $userId The user performing the deletion
     * @return bool Success status
     * @throws Exception if invoice is already matched
     */
    public function deleteXeroInvoice($xeroInvoiceId, $sessionId, $userId) {
        // Get the invoice details first
        $xeroInvoice = $this->db->fetchOne(
            "SELECT * FROM xero_imported_invoices WHERE id = :id AND session_id = :session_id",
            ['id' => $xeroInvoiceId, 'session_id' => $sessionId]
        );

        if (!$xeroInvoice) {
            throw new Exception("Xero invoice not found or doesn't belong to this session");
        }

        // Check if invoice is already matched
        if ($xeroInvoice['match_status'] === 'matched') {
            throw new Exception("Cannot delete a matched invoice. Please reject the match first.");
        }

        // Log the deletion in audit log
        $this->db->insert('matching_audit_log', [
            'session_id' => $sessionId,
            'xero_invoice_id' => $xeroInvoiceId,
            'system_invoice_id' => null,
            'match_score' => 0,
            'match_details' => json_encode([
                'reason' => 'deleted by user',
                'invoice_number' => $xeroInvoice['xero_invoice_number'],
                'contact_name' => $xeroInvoice['contact_name'],
                'amount' => $xeroInvoice['amount']
            ]),
            'action' => 'deleted',
            'performed_by' => $userId
        ]);

        // Delete the invoice
        $this->db->delete('xero_imported_invoices', 'id = :id', ['id' => $xeroInvoiceId]);

        // Update session counts
        $this->updateSessionCounts($sessionId);

        return true;
    }

    /**
     * Update session invoice counts
     *
     * @param int $sessionId The session ID
     */
    private function updateSessionCounts($sessionId) {
        // Count total invoices
        $totalCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM xero_imported_invoices WHERE session_id = :session_id",
            ['session_id' => $sessionId]
        )['count'];

        // Count matched invoices (auto_matched or manual_matched)
        $matchedCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM xero_imported_invoices
             WHERE session_id = :session_id
             AND match_status IN ('auto_matched', 'manual_matched')",
            ['session_id' => $sessionId]
        )['count'];

        // Count reconciled invoices
        $reconciledCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM xero_imported_invoices
             WHERE session_id = :session_id
             AND is_reconciled = TRUE",
            ['session_id' => $sessionId]
        )['count'];

        // Update session - CORRECT parameter order: table, data, where, params
        $this->db->update('xero_import_sessions',
            [
                'total_invoices' => $totalCount,
                'matched_count' => $matchedCount,
                'reconciled_count' => $reconciledCount
            ],
            'id = :id',
            ['id' => $sessionId]
        );
    }
}

