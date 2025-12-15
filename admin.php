<?php
// admin.php - Redirección al API unificado
header('Content-Type: application/json');

// Permitir POST y GET
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pasar todos los datos al api.php
    $postData = $_POST;
    
    // Si no hay acción, establecer una por defecto
    if (empty($postData['action'])) {
        if (isset($postData['password'])) {
            // Verificar contraseña
            if ($postData['password'] === 'AdminCryptoBank2024!') {
                echo json_encode(['success' => true, 'message' => 'Acceso concedido']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
        }
        exit;
    }
    
    // Pasar al api.php
    require_once('api.php');
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>