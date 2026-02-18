<?php
/**
 * Prepayment Calculator Service
 * Calculates prepayments and P&L recognition for invoices
 */

namespace App\Services;

use DateTime;

class PrepaymentCalculator {
    
    /**
     * Calculate prepayment for an invoice on a specific date
     *
     * New method: 0.5 month in first month, 1/12th throughout year, 0.5 month in final month
     * This reflects that invoices are raised at the end of the month following install
     *
     * @param array $invoice Invoice data
     * @param string $calculationDate Date to calculate prepayment for (Y-m-d)
     * @return array Prepayment calculation details
     */
    public function calculate($invoice, $calculationDate = null) {
        if ($calculationDate === null) {
            $calculationDate = date('Y-m-d');
        }

        $invoiceDate = new DateTime($invoice['invoice_date']);
        $calcDate = new DateTime($calculationDate);

        // Check if invoice is in the future - if so, no prepayment exists yet
        if ($invoiceDate > $calcDate) {
            return [
                'invoice_id' => $invoice['id'],
                'legal_entity_id' => $invoice['legal_entity_id'] ?? null,
                'calculation_date' => $calculationDate,
                'invoice_date' => $invoice['invoice_date'],
                'invoice_amount' => $invoice['invoice_amount'],
                'payment_frequency' => $invoice['payment_frequency'],
                'days_since_invoice' => 0,
                'days_in_period' => $this->getDaysInPeriod($invoice['payment_frequency']),
                'days_remaining' => 0,
                'prepayment_balance' => 0.00,
                'pl_credit_amount' => 0.00,
            ];
        }

        $daysSinceInvoice = $invoiceDate->diff($calcDate)->days;

        // Determine days in period based on payment frequency
        $daysInPeriod = $this->getDaysInPeriod($invoice['payment_frequency']);

        // Calculate days remaining
        $daysRemaining = max(0, $daysInPeriod - $daysSinceInvoice);

        // Calculate prepayment balance
        $prepaymentBalance = 0;
        $plCreditAmount = 0;

        if ($invoice['payment_frequency'] === 'monthly') {
            // Monthly goes straight to P&L
            $prepaymentBalance = 0;
            $plCreditAmount = $invoice['invoice_amount'];
        } else {
            // Annual or Quarterly - use new 0.5/1/12th/0.5 method
            $prepaymentBalance = $this->calculatePrepaymentBalance(
                $invoice['invoice_amount'],
                $invoiceDate,
                $calcDate,
                $invoice['payment_frequency']
            );
            $plCreditAmount = $invoice['invoice_amount'] - $prepaymentBalance;
        }

        return [
            'invoice_id' => $invoice['id'],
            'legal_entity_id' => $invoice['legal_entity_id'] ?? null,
            'calculation_date' => $calculationDate,
            'invoice_date' => $invoice['invoice_date'],
            'invoice_amount' => $invoice['invoice_amount'],
            'payment_frequency' => $invoice['payment_frequency'],
            'days_since_invoice' => $daysSinceInvoice,
            'days_in_period' => $daysInPeriod,
            'days_remaining' => $daysRemaining,
            'prepayment_balance' => round($prepaymentBalance, 2),
            'pl_credit_amount' => round($plCreditAmount, 2),
        ];
    }
    
    /**
     * Calculate prepayment balance using 0.5/1/12th/0.5 method
     *
     * Method:
     * - First month: 0.5 month worth of revenue to P&L
     * - Middle months: 1/12th per month to P&L
     * - Final month: 0.5 month worth of revenue to P&L
     *
     * This reflects that invoices are raised at end of month following install
     *
     * @param float $invoiceAmount Total invoice amount
     * @param DateTime $invoiceDate Invoice date
     * @param DateTime $calcDate Calculation date
     * @param string $frequency Payment frequency (annual/quarterly)
     * @return float Prepayment balance remaining
     */
    private function calculatePrepaymentBalance($invoiceAmount, $invoiceDate, $calcDate, $frequency) {
        // Determine number of months in period
        $monthsInPeriod = $frequency === 'annual' ? 12 : 3;

        // Calculate which month we're in (0-based)
        $monthsSinceInvoice = $this->getMonthsBetween($invoiceDate, $calcDate);

        // If we're past the period, prepayment is 0
        if ($monthsSinceInvoice >= $monthsInPeriod) {
            return 0;
        }

        // Calculate total P&L recognized so far
        $plRecognized = 0;

        // Month 0 (invoice month): 0.5/12th
        if ($monthsSinceInvoice >= 0) {
            $plRecognized += ($invoiceAmount / 12) * 0.5;
        }

        // Middle months: 1/12th each
        $middleMonths = min($monthsSinceInvoice, $monthsInPeriod - 1);
        if ($middleMonths > 0) {
            $plRecognized += ($invoiceAmount / 12) * $middleMonths;
        }

        // Final month: 0.5/12th (only if we've reached the final month)
        if ($monthsSinceInvoice >= $monthsInPeriod - 1) {
            $plRecognized += ($invoiceAmount / 12) * 0.5;
        }

        // Prepayment balance is invoice amount minus P&L recognized
        return max(0, $invoiceAmount - $plRecognized);
    }

    /**
     * Calculate number of complete months between two dates
     *
     * @param DateTime $start Start date
     * @param DateTime $end End date
     * @return int Number of complete months
     */
    private function getMonthsBetween($start, $end) {
        $interval = $start->diff($end);
        return ($interval->y * 12) + $interval->m;
    }

    /**
     * Get days in period based on payment frequency
     */
    private function getDaysInPeriod($frequency) {
        switch ($frequency) {
            case 'annual':
                return 365;
            case 'quarterly':
                return 91.25; // 365 / 4
            case 'monthly':
                return 30; // Average month
            default:
                return 365;
        }
    }
    
    /**
     * Calculate monthly prepayment schedule for an invoice
     */
    public function calculateMonthlySchedule($invoice, $months = 12) {
        $schedule = [];
        $startDate = new DateTime($invoice['invoice_date']);
        
        for ($i = 0; $i < $months; $i++) {
            $calcDate = clone $startDate;
            $calcDate->modify("+{$i} months");
            
            $schedule[] = $this->calculate($invoice, $calcDate->format('Y-m-d'));
        }
        
        return $schedule;
    }
    
    /**
     * Get total prepayments for all invoices on a specific date
     */
    public function getTotalPrepayments($invoices, $calculationDate = null) {
        $total = 0;
        
        foreach ($invoices as $invoice) {
            $calc = $this->calculate($invoice, $calculationDate);
            $total += $calc['prepayment_balance'];
        }
        
        return round($total, 2);
    }
}

