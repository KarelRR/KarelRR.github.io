<?php
// api.php - Backend completo para CryptoBank

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Base de datos en un único archivo JSON
define('DB_FILE', 'database.json');

// Función para leer datos del archivo JSON
function readDatabase() {
    if (!file_exists(DB_FILE)) {
        // Crear estructura inicial de la base de datos
        $initialData = [
            'users' => [],
            'transactions' => []
        ];
        file_put_contents(DB_FILE, json_encode($initialData, JSON_PRETTY_PRINT));
        return $initialData;
    }
    
    $json = file_get_contents(DB_FILE);
    $data = json_decode($json, true);
    
    // Verificar si el JSON es válido
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Si hay error, crear nueva base de datos
        $initialData = [
            'users' => [],
            'transactions' => []
        ];
        file_put_contents(DB_FILE, json_encode($initialData, JSON_PRETTY_PRINT));
        return $initialData;
    }
    
    return $data;
}

// Función para escribir datos en el archivo JSON
function writeDatabase($data) {
    file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Generar ID único
function generateId() {
    return uniqid('', true);
}

// Generar código de referido
function generateReferralCode($username) {
    $prefix = strtoupper(substr($username, 0, 3));
    $random = strtoupper(bin2hex(random_bytes(3)));
    return $prefix . $random;
}

// Validar token
function validateToken($userId, $token) {
    $db = readDatabase();
    
    if (isset($db['users'][$userId]) && isset($db['users'][$userId]['token']) && $db['users'][$userId]['token'] === $token) {
        return true;
    }
    
    return false;
}

// Calcular ganancias acumuladas
function calculateEarnings($userId) {
    $db = readDatabase();
    
    if (!isset($db['users'][$userId]) || !isset($db['users'][$userId]['investment_start_date'])) {
        return 0;
    }
    
    $user = $db['users'][$userId];
    $investedBalance = floatval($user['invested_balance']);
    
    if ($investedBalance <= 0) {
        return 0;
    }
    
    $startDate = new DateTime($user['investment_start_date']);
    $currentDate = new DateTime();
    $interval = $startDate->diff($currentDate);
    $daysPassed = min($interval->days, 165); // Máximo 165 días
    
    // 1.5% diario
    $dailyEarnings = $investedBalance * 0.015;
    $totalEarned = $dailyEarnings * $daysPassed;
    
    return $totalEarned;
}

// Obtener estadísticas del dashboard
function getDashboardStats() {
    $db = readDatabase();
    
    $stats = [
        'total_users' => count($db['users']),
        'pending_deposits' => 0,
        'pending_withdrawals' => 0,
        'total_invested' => 0
    ];
    
    // Calcular total invertido
    foreach ($db['users'] as $user) {
        $stats['total_invested'] += floatval($user['invested_balance'] ?? 0);
    }
    
    // Contar transacciones pendientes
    foreach ($db['transactions'] as $transaction) {
        if ($transaction['status'] === 'pending') {
            if ($transaction['type'] === 'deposit') {
                $stats['pending_deposits']++;
            } else if ($transaction['type'] === 'withdrawal') {
                $stats['pending_withdrawals']++;
            }
        }
    }
    
    return $stats;
}

// Validar si email ya está en uso
function isEmailTaken($email) {
    $db = readDatabase();
    
    foreach ($db['users'] as $user) {
        if ($user['email'] === $email) {
            return true;
        }
    }
    
    return false;
}

// Validar si teléfono ya está en uso
function isPhoneTaken($phone) {
    $db = readDatabase();
    
    foreach ($db['users'] as $user) {
        if ($user['phone'] === $phone) {
            return true;
        }
    }
    
    return false;
}

// Validar si username ya está en uso
function isUsernameTaken($username) {
    $db = readDatabase();
    
    foreach ($db['users'] as $user) {
        if ($user['username'] === $username) {
            return true;
        }
    }
    
    return false;
}

// Procesar la solicitud
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // También aceptar datos POST estándar para compatibilidad
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    $response = ['success' => false, 'message' => 'Acción no válida'];
    
    switch ($action) {
        case 'register':
            $response = handleRegister($input);
            break;
            
        case 'login':
            $response = handleLogin($input);
            break;
            
        case 'get_user':
            $response = handleGetUser($input);
            break;
            
        case 'deposit':
            $response = handleDeposit($input);
            break;
            
        case 'withdraw':
            $response = handleWithdraw($input);
            break;
            
        case 'invest':
            $response = handleInvest($input);
            break;
            
        case 'get_dashboard_stats':
            $response = handleGetDashboardStats();
            break;
            
        case 'get_pending_deposits':
            $response = handleGetPendingDeposits($input);
            break;
            
        case 'get_pending_withdrawals':
            $response = handleGetPendingWithdrawals($input);
            break;
            
        case 'confirm_deposit':
            $response = handleConfirmDeposit($input);
            break;
            
        case 'confirm_withdrawal':
            $response = handleConfirmWithdrawal($input);
            break;
            
        case 'check_email':
            $response = handleCheckEmail($input);
            break;
            
        case 'check_phone':
            $response = handleCheckPhone($input);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }
    
    echo json_encode($response);
} else if ($method === 'GET') {
    // Para debug: mostrar información de la base de datos
    if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
        $db = readDatabase();
        echo json_encode([
            'success' => true,
            'total_users' => count($db['users']),
            'total_transactions' => count($db['transactions']),
            'file_exists' => file_exists(DB_FILE),
            'file_size' => filesize(DB_FILE)
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'message' => 'Método GET no permitido para operaciones']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

// Manejar registro con validación de email y teléfono
function handleRegister($data) {
    $db = readDatabase();
    $users = &$db['users'];
    
    // Validar datos requeridos
    $required = ['name', 'username', 'email', 'phone', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "El campo $field es requerido"];
        }
    }
    
    // Validar formato de email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Formato de email inválido'];
    }
    
    // Validar formato de teléfono (mínimo 8 caracteres)
    if (strlen($data['phone']) < 8) {
        return ['success' => false, 'message' => 'Teléfono inválido'];
    }
    
    // Verificar si el email ya está registrado
    if (isEmailTaken($data['email'])) {
        return ['success' => false, 'message' => 'Este email ya está registrado'];
    }
    
    // Verificar si el teléfono ya está registrado
    if (isPhoneTaken($data['phone'])) {
        return ['success' => false, 'message' => 'Este número de teléfono ya está registrado'];
    }
    
    // Verificar si el nombre de usuario ya está en uso
    if (isUsernameTaken($data['username'])) {
        return ['success' => false, 'message' => 'Este nombre de usuario ya está en uso'];
    }
    
    // Generar código de referido
    $referralCode = generateReferralCode($data['username']);
    
    // Crear nuevo usuario
    $userId = generateId();
    $newUser = [
        'id' => $userId,
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        'referral_code' => $referralCode,
        'referred_by' => $data['referral_code'] ?? null,
        'invested_balance' => 0,
        'available_balance' => 0,
        'total_earned' => 0,
        'referrals_count' => 0,
        'referral_earnings' => 0,
        'investment_start_date' => null,
        'registration_date' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'token' => null
    ];
    
    // Procesar referido si existe
    if (!empty($data['referral_code'])) {
        foreach ($users as &$user) {
            if ($user['referral_code'] === $data['referral_code']) {
                $user['referrals_count']++;
                break;
            }
        }
    }
    
    $users[$userId] = $newUser;
    writeDatabase($db);
    
    return [
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'user_id' => $userId
    ];
}

// Manejar login
function handleLogin($data) {
    $db = readDatabase();
    $users = &$db['users'];
    
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    foreach ($users as &$user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            // Generar token
            $token = bin2hex(random_bytes(32));
            
            // Actualizar usuario
            $user['token'] = $token;
            $user['last_login'] = date('Y-m-d H:i:s');
            
            // Calcular ganancias actualizadas
            $user['total_earned'] = calculateEarnings($user['id']);
            
            writeDatabase($db);
            
            // Eliminar contraseña del response
            unset($user['password']);
            
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'token' => $token,
                'user' => $user
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Credenciales incorrectas'];
}

// Obtener datos del usuario
function handleGetUser($data) {
    if (!validateToken($data['user_id'], $data['token'])) {
        return ['success' => false, 'message' => 'Token inválido'];
    }
    
    $db = readDatabase();
    $users = $db['users'];
    $transactions = $db['transactions'];
    
    $userId = $data['user_id'];
    
    if (isset($users[$userId])) {
        $user = $users[$userId];
        
        // Calcular ganancias actualizadas
        $user['total_earned'] = calculateEarnings($userId);
        
        // Obtener transacciones del usuario
        $userTransactions = [];
        foreach ($transactions as $transaction) {
            if ($transaction['user_id'] === $userId) {
                $userTransactions[] = $transaction;
            }
        }
        
        // Ordenar transacciones por fecha (más recientes primero)
        usort($userTransactions, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Eliminar información sensible
        unset($user['password']);
        
        $user['transactions'] = $userTransactions;
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    return ['success' => false, 'message' => 'Usuario no encontrado'];
}

// Manejar depósito
function handleDeposit($data) {
    if (!validateToken($data['user_id'], $data['token'])) {
        return ['success' => false, 'message' => 'Token inválido'];
    }
    
    $amount = floatval($data['amount']);
    $transactionHash = $data['transaction_hash'] ?? '';
    
    if ($amount < 1) {
        return ['success' => false, 'message' => 'El monto mínimo es 1 USDT'];
    }
    
    if (empty($transactionHash)) {
        return ['success' => false, 'message' => 'El hash de transacción es requerido'];
    }
    
    $db = readDatabase();
    $users = &$db['users'];
    $transactions = &$db['transactions'];
    
    $userId = $data['user_id'];
    
    if (isset($users[$userId])) {
        // Registrar transacción
        $transactionId = generateId();
        $transaction = [
            'id' => $transactionId,
            'user_id' => $userId,
            'type' => 'deposit',
            'amount' => $amount,
            'transaction_hash' => $transactionHash,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s')
        ];
        
        $transactions[] = $transaction;
        writeDatabase($db);
        
        return ['success' => true, 'message' => 'Depósito registrado exitosamente. El balance se acreditará cuando sea confirmado.'];
    }
    
    return ['success' => false, 'message' => 'Usuario no encontrado'];
}

// Manejar retiro
function handleWithdraw($data) {
    if (!validateToken($data['user_id'], $data['token'])) {
        return ['success' => false, 'message' => 'Token inválido'];
    }
    
    $amount = floatval($data['amount']);
    $wallet = $data['wallet'] ?? '';
    
    if ($amount < 1) {
        return ['success' => false, 'message' => 'El monto mínimo es 1 USDT'];
    }
    
    if (empty($wallet)) {
        return ['success' => false, 'message' => 'La wallet es requerida'];
    }
    
    $db = readDatabase();
    $users = &$db['users'];
    $transactions = &$db['transactions'];
    
    $userId = $data['user_id'];
    
    if (isset($users[$userId])) {
        if ($users[$userId]['available_balance'] < $amount) {
            return ['success' => false, 'message' => 'Fondos insuficientes'];
        }
        
        // Actualizar balance del usuario - se descuenta inmediatamente
        $users[$userId]['available_balance'] -= $amount;
        
        // Registrar transacción
        $transactionId = generateId();
        $transaction = [
            'id' => $transactionId,
            'user_id' => $userId,
            'type' => 'withdrawal',
            'amount' => $amount,
            'wallet' => $wallet,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s')
        ];
        
        $transactions[] = $transaction;
        
        writeDatabase($db);
        
        return ['success' => true, 'message' => 'Retiro solicitado exitosamente. El monto ha sido descontado de tu balance disponible.'];
    }
    
    return ['success' => false, 'message' => 'Usuario no encontrado'];
}

// Manejar inversión
function handleInvest($data) {
    if (!validateToken($data['user_id'], $data['token'])) {
        return ['success' => false, 'message' => 'Token inválido'];
    }
    
    $amount = floatval($data['amount']);
    
    if ($amount < 1) {
        return ['success' => false, 'message' => 'El monto mínimo de inversión es 1 USDT'];
    }
    
    $db = readDatabase();
    $users = &$db['users'];
    
    $userId = $data['user_id'];
    
    if (isset($users[$userId])) {
        if ($users[$userId]['available_balance'] < $amount) {
            return ['success' => false, 'message' => 'Fondos insuficientes en el balance disponible'];
        }
        
        // Transferir de balance disponible a balance invertido
        $users[$userId]['available_balance'] -= $amount;
        $users[$userId]['invested_balance'] += $amount;
        
        // Si es la primera inversión, establecer fecha de inicio
        if ($users[$userId]['investment_start_date'] === null) {
            $users[$userId]['investment_start_date'] = date('Y-m-d H:i:s');
        }
        
        // Calcular ganancias actualizadas
        $users[$userId]['total_earned'] = calculateEarnings($userId);
        
        writeDatabase($db);
        
        return [
            'success' => true,
            'message' => 'Inversión realizada exitosamente. Comenzarás a ganar el 1.5% diario.',
            'available_balance' => $users[$userId]['available_balance'],
            'invested_balance' => $users[$userId]['invested_balance']
        ];
    }
    
    return ['success' => false, 'message' => 'Usuario no encontrado'];
}

// Confirmar depósito
function handleConfirmDeposit($data) {
    // Verificar contraseña de administración (simple)
    $adminPassword = $data['password'] ?? '';
    if ($adminPassword !== 'AdminCryptoBank2024!') {
        return ['success' => false, 'message' => 'Acceso no autorizado'];
    }
    
    $transactionId = $data['transaction_id'] ?? '';
    if (empty($transactionId)) {
        return ['success' => false, 'message' => 'ID de transacción requerido'];
    }
    
    $db = readDatabase();
    $users = &$db['users'];
    $transactions = &$db['transactions'];
    
    // Buscar la transacción
    foreach ($transactions as &$transaction) {
        if ($transaction['id'] === $transactionId && $transaction['type'] === 'deposit' && $transaction['status'] === 'pending') {
            $userId = $transaction['user_id'];
            $amount = floatval($transaction['amount']);
            
            if (isset($users[$userId])) {
                // Añadir al balance disponible (no invertido inmediatamente)
                $users[$userId]['available_balance'] += $amount;
                
                // Marcar transacción como completada
                $transaction['status'] = 'completed';
                $transaction['completion_date'] = date('Y-m-d H:i:s');
                
                // Procesar comisión de referidos (10% de todos los depósitos)
                if (!empty($users[$userId]['referred_by'])) {
                    $referralCode = $users[$userId]['referred_by'];
                    
                    // Buscar al referente
                    foreach ($users as &$referrer) {
                        if ($referrer['referral_code'] === $referralCode) {
                            $commission = $amount * 0.10; // 10% de comisión
                            $referrer['available_balance'] += $commission;
                            $referrer['referral_earnings'] += $commission;
                            break;
                        }
                    }
                }
                
                writeDatabase($db);
                return ['success' => true, 'message' => 'Depósito confirmado exitosamente'];
            }
        }
    }
    
    return ['success' => false, 'message' => 'Transacción no encontrada o ya confirmada'];
}

// Confirmar retiro
function handleConfirmWithdrawal($data) {
    // Verificar contraseña de administración (simple)
    $adminPassword = $data['password'] ?? '';
    if ($adminPassword !== 'AdminCryptoBank2024!') {
        return ['success' => false, 'message' => 'Acceso no autorizado'];
    }
    
    $transactionId = $data['transaction_id'] ?? '';
    if (empty($transactionId)) {
        return ['success' => false, 'message' => 'ID de transacción requerido'];
    }
    
    $db = readDatabase();
    $transactions = &$db['transactions'];
    
    // Buscar la transacción
    foreach ($transactions as &$transaction) {
        if ($transaction['id'] === $transactionId && $transaction['type'] === 'withdrawal' && $transaction['status'] === 'pending') {
            // Marcar transacción como completada
            $transaction['status'] = 'completed';
            $transaction['completion_date'] = date('Y-m-d H:i:s');
            
            writeDatabase($db);
            return ['success' => true, 'message' => 'Retiro confirmado exitosamente'];
        }
    }
    
    return ['success' => false, 'message' => 'Transacción no encontrada o ya confirmada'];
}

// Obtener depósitos pendientes
function handleGetPendingDeposits($data) {
    // Verificar contraseña de administración (simple)
    $adminPassword = $data['password'] ?? '';
    if ($adminPassword !== 'AdminCryptoBank2024!') {
        return ['success' => false, 'message' => 'Acceso no autorizado'];
    }
    
    $db = readDatabase();
    $pendingDeposits = [];
    
    foreach ($db['transactions'] as $transaction) {
        if ($transaction['type'] === 'deposit' && $transaction['status'] === 'pending') {
            $userId = $transaction['user_id'];
            $user = $db['users'][$userId] ?? null;
            
            if ($user) {
                $pendingDeposits[] = [
                    'transaction_id' => $transaction['id'],
                    'user_id' => $userId,
                    'user_name' => $user['name'],
                    'user_email' => $user['email'],
                    'amount' => $transaction['amount'],
                    'transaction_hash' => $transaction['transaction_hash'],
                    'date' => $transaction['date']
                ];
            }
        }
    }
    
    return ['success' => true, 'deposits' => $pendingDeposits];
}

// Obtener retiros pendientes
function handleGetPendingWithdrawals($data) {
    // Verificar contraseña de administración (simple)
    $adminPassword = $data['password'] ?? '';
    if ($adminPassword !== 'AdminCryptoBank2024!') {
        return ['success' => false, 'message' => 'Acceso no autorizado'];
    }
    
    $db = readDatabase();
    $pendingWithdrawals = [];
    
    foreach ($db['transactions'] as $transaction) {
        if ($transaction['type'] === 'withdrawal' && $transaction['status'] === 'pending') {
            $userId = $transaction['user_id'];
            $user = $db['users'][$userId] ?? null;
            
            if ($user) {
                $pendingWithdrawals[] = [
                    'transaction_id' => $transaction['id'],
                    'user_id' => $userId,
                    'user_name' => $user['name'],
                    'user_email' => $user['email'],
                    'amount' => $transaction['amount'],
                    'wallet' => $transaction['wallet'],
                    'date' => $transaction['date']
                ];
            }
        }
    }
    
    return ['success' => true, 'withdrawals' => $pendingWithdrawals];
}

// Obtener estadísticas del dashboard
function handleGetDashboardStats() {
    $stats = getDashboardStats();
    return ['success' => true, 'stats' => $stats];
}

// Verificar si email está disponible
function handleCheckEmail($data) {
    $email = $data['email'] ?? '';
    
    if (empty($email)) {
        return ['success' => false, 'message' => 'Email requerido'];
    }
    
    if (isEmailTaken($email)) {
        return ['success' => true, 'available' => false, 'message' => 'Email ya registrado'];
    }
    
    return ['success' => true, 'available' => true, 'message' => 'Email disponible'];
}

// Verificar si teléfono está disponible
function handleCheckPhone($data) {
    $phone = $data['phone'] ?? '';
    
    if (empty($phone)) {
        return ['success' => false, 'message' => 'Teléfono requerido'];
    }
    
    if (isPhoneTaken($phone)) {
        return ['success' => true, 'available' => false, 'message' => 'Teléfono ya registrado'];
    }
    
    return ['success' => true, 'available' => true, 'message' => 'Teléfono disponible'];
}
?>