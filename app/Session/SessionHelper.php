<?php

namespace App\Session;

class SessionHelper
{
    public function __construct()
    {
        session_start();
    }
    
    public function setValues($data)
    {
        foreach ($data as $item) {
            $_SESSION[$item['key']] = $item['value'];
        }
    }

    public function getUserId()
    {
        $userId = $_SESSION['userid'] ?? 0;
        return $userId;
    }

    public function getUsername()
    {
        $username = $_SESSION['username'] ?? '';
        return $username;
    }
}