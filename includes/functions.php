<?php
// Get delivery fee for a specific location
function getDeliveryFee($conn, $state, $city) {
    $fee = 0;
    
    try {
        $stmt = $conn->prepare("SELECT fee FROM delivery_fees WHERE state = ? AND city = ? LIMIT 1");
        $stmt->bind_param("ss", $state, $city);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $fee = $row['fee'];
        }
    } catch (Exception $e) {
        // Return default fee if error
        $fee = 0;
    }
    
    return $fee;
}

// Get all delivery fees
function getAllDeliveryFees($conn) {
    $fees = [];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM delivery_fees ORDER BY state, city");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $fees[] = $row;
        }
    } catch (Exception $e) {
        // Return empty array if error
    }
    
    return $fees;
}

// Get all unique states
function getAllStates($conn) {
    $states = [];
    
    try {
        $stmt = $conn->prepare("SELECT DISTINCT state FROM delivery_fees ORDER BY state");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $states[] = $row['state'];
        }
    } catch (Exception $e) {
        // Return empty array if error
    }
    
    return $states;
}

// Get cities for a specific state
function getCitiesByState($conn, $state) {
    $cities = [];
    
    try {
        $stmt = $conn->prepare("SELECT city FROM delivery_fees WHERE state = ? ORDER BY city");
        $stmt->bind_param("s", $state);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $cities[] = $row['city'];
        }
    } catch (Exception $e) {
        // Return empty array if error
    }
    
    return $cities;
}
