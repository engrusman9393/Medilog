<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

function getUserInfo() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'shop_name' => $_SESSION['shop_name'] ?? '',
        'user_type' => $_SESSION['user_type'] ?? ''
    ];
}

// Currency symbols mapping
function getCurrencySymbols() {
    return [
        'PKR' => '₨',
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'CHF' => 'Fr',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'AED' => 'د.إ',
        'SAR' => '﷼',
        'QAR' => '﷼',
        'KWD' => 'د.ك',
        'BHD' => 'د.ب',
        'OMR' => '﷼',
        'EGP' => '£',
        'TRY' => '₺',
        'ZAR' => 'R',
        'NGN' => '₦',
        'KES' => 'KSh',
        'GHS' => '₵',
        'BDT' => '৳',
        'LKR' => '₨',
        'NPR' => '₨',
        'AFN' => '؋',
        'MYR' => 'RM',
        'SGD' => 'S$',
        'THB' => '฿',
        'IDR' => 'Rp',
        'PHP' => '₱',
        'VND' => '₫',
        'KRW' => '₩',
        'HKD' => 'HK$',
        'TWD' => 'NT$',
        'BRL' => 'R$',
        'MXN' => '$',
        'ARS' => '$',
        'CLP' => '$',
        'COP' => '$',
        'PEN' => 'S/',
        'RUB' => '₽',
        'PLN' => 'zł',
        'CZK' => 'Kč',
        'HUF' => 'Ft',
        'RON' => 'lei',
        'BGN' => 'лв',
        'HRK' => 'kn',
        'ILS' => '₪',
        'JOD' => 'د.ا',
        'LBP' => 'ل.ل',
        'IRR' => '﷼',
        'IQD' => 'ع.د'
    ];
}

// Get user's preferred currency
function getUserCurrency($userId = null) {
    if (!$userId && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return 'PKR'; // Default currency
    }
    
    try {
        $db = new Database();
        return $db->getUserSetting($userId, 'currency', 'PKR');
    } catch (Exception $e) {
        return 'PKR';
    }
}

// Format currency based on user preference
function formatCurrency($amount, $userId = null) {
    $currency = getUserCurrency($userId);
    $symbols = getCurrencySymbols();
    $symbol = $symbols[$currency] ?? $currency;
    
    // Format number with appropriate decimals
    $decimals = in_array($currency, ['JPY', 'KRW', 'VND', 'IDR']) ? 0 : 2;
    $formattedAmount = number_format($amount, $decimals);
    
    // Position symbol based on currency convention
    $prefixSymbols = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'HKD', 'SGD', 'MXN', 'ARS', 'CLP', 'COP', 'BRL'];
    
    if (in_array($currency, $prefixSymbols)) {
        return $symbol . $formattedAmount;
    } else {
        return $formattedAmount . ' ' . $symbol;
    }
}

// Get currency symbol only
function getCurrencySymbol($userId = null) {
    $currency = getUserCurrency($userId);
    $symbols = getCurrencySymbols();
    return $symbols[$currency] ?? $currency;
}

// Set user currency
function setUserCurrency($currency, $userId = null) {
    if (!$userId && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return false;
    }
    
    try {
        $db = new Database();
        return $db->setUserSetting($userId, 'currency', $currency);
    } catch (Exception $e) {
        return false;
    }
}

requireLogin();
$user = getUserInfo();
?>