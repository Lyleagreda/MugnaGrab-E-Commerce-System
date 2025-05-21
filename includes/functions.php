<?php
// Common functions for the website

/**
 * Format price with Philippine Peso sign
 * 
 * @param float $price The price to format
 * @return string Formatted price with Peso sign
 */
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

/**
 * Format and validate Philippine phone number
 * 
 * @param string $phone The phone number to format
 * @return string|false Formatted phone number or false if invalid
 */
function formatPhilippinePhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check if it starts with +63
    if (strpos($phone, '+63') === 0) {
        // +63 format
        $phone = '+63' . substr($phone, 3);
        
        // Check if it has the correct length (12 digits including +63)
        if (strlen($phone) !== 12) {
            return false;
        }
        
        // Check if the digit after +63 is 9
        if (substr($phone, 3, 1) !== '9') {
            return false;
        }
    } 
    // Check if it starts with 09
    elseif (strpos($phone, '09') === 0) {
        // 09XX format
        
        // Check if it has the correct length (11 digits)
        if (strlen($phone) !== 11) {
            return false;
        }
        
        // Convert to +63 format
        $phone = '+63' . substr($phone, 1);
    }
    // Check if it starts with 9
    elseif (strpos($phone, '9') === 0) {
        // 9XX format (without leading 0)
        
        // Check if it has the correct length (10 digits)
        if (strlen($phone) !== 10) {
            return false;
        }
        
        // Convert to +63 format
        $phone = '+63' . $phone;
    }
    else {
        return false;
    }
    
    return $phone;
}

