<?php
/**
 * Base Model Class
 */

namespace App\Models;

use App\Database;

abstract class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function all() {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC";
        return $this->db->fetchAll($sql);
    }
    
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    public function where($column, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = :value";
        return $this->db->fetchAll($sql, ['value' => $value]);
    }
    
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            $this->table,
            $data,
            "{$this->primaryKey} = :id",
            ['id' => $id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            $this->table,
            "{$this->primaryKey} = :id",
            ['id' => $id]
        );
    }
    
    public function query($sql, $params = []) {
        return $this->db->query($sql, $params);
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->db->fetchAll($sql, $params);
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->db->fetchOne($sql, $params);
    }
}

