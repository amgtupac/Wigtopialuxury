<?php
require_once '../app/core/db.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$action = $input['action'];
$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {
    case 'add':
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        // Check if product exists and has stock
        try {
            $stmt = $pdo->prepare("SELECT name, price, stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            $current_quantity = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
            $new_quantity = $current_quantity + $quantity;
            
            if ($new_quantity > $product['stock']) {
                echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                exit();
            }
            
            $_SESSION['cart'][$product_id] = $new_quantity;
            echo json_encode(['success' => true, 'message' => '']);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error adding product to cart']);
        }
        break;
        
    case 'update':
        if ($product_id <= 0 || $quantity < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit();
        }
        
        if ($quantity == 0) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            // Check stock
            try {
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product || $quantity > $product['stock']) {
                    echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                    exit();
                }
                
                $_SESSION['cart'][$product_id] = $quantity;
                
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating cart']);
                exit();
            }
        }
        
        echo json_encode(['success' => true, 'message' => '']);
        break;
        
    case 'remove':
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        unset($_SESSION['cart'][$product_id]);
        echo json_encode(['success' => true, 'message' => '']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>