<?php
/**
 * User Model
 */

namespace App\Models;

class User extends Model {
    protected $table = 'users';
    
    /**
     * Create a new user with hashed password
     */
    public function createUser($data) {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        
        return $this->create($data);
    }
    
    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        return $this->update($userId, ['password_hash' => $passwordHash]);
    }
    
    /**
     * Get user by username
     */
    public function getByUsername($username) {
        return $this->fetchOne(
            "SELECT * FROM {$this->table} WHERE username = :username",
            ['username' => $username]
        );
    }
    
    /**
     * Get active users
     */
    public function getActive() {
        return $this->fetchAll(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY full_name"
        );
    }
}

