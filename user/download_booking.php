<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['booking_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit();
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get booking details with facility and user information
    $stmt = $pdo->prepare("
        SELECT b.*, f.name as facility_name, f.description as facility_description,
               u.name as user_name, u.email, u.phone_number, u.address
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.user_id = ? AND b.status = 'approved'
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Booking not found or not approved']);
        exit();
    }

    // Get equipment details
    $stmt = $pdo->prepare("
        SELECT i.name, be.quantity
        FROM booking_equipment be
        JOIN inventory i ON be.equipment_id = i.id
        WHERE be.booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate HTML content for the certificate
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Certificate Preview - ' . $booking_id . '</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <style>
                * {
                    box-sizing: border-box;
                }
                
            body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                margin: 0;
                    padding: 15px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
                
            .preview-container {
                background: white;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    padding: 25px;
                margin-bottom: 20px;
                    max-width: 100%;
                    width: 100%;
                max-width: 900px;
            }
                
            .preview-header {
                text-align: center;
                    margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 2px solid #e9ecef;
            }
                
            .preview-title {
                    font-size: 28px;
                    font-weight: 700;
                color: #2c3e50;
                    margin-bottom: 8px;
                    background: linear-gradient(45deg, #667eea, #764ba2);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                
            .preview-subtitle {
                color: #6c757d;
                font-size: 16px;
                    font-weight: 500;
            }
                
            .action-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin-bottom: 30px;
                    flex-wrap: wrap;
            }
                
            .btn {
                    padding: 15px 30px;
                border: none;
                    border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                    gap: 10px;
                    min-width: 180px;
                    justify-content: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .btn::before {
                    content: "";
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                    transition: left 0.5s;
                }
                
                .btn:hover::before {
                    left: 100%;
                }
                
            .btn-primary {
                    background: linear-gradient(45deg, #28a745, #20c997);
                color: white;
                    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            }
                
            .btn-primary:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            }
                
            .btn-secondary {
                    background: linear-gradient(45deg, #6c757d, #495057);
                color: white;
                    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            }
                
            .btn-secondary:hover {
                    background: linear-gradient(45deg, #5a6268, #343a40);
                    transform: translateY(-3px);
                    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
            }
                
            .btn i {
                    font-size: 20px;
            }
                
            .certificate {
                background: white;
                    width: 100%;
                    max-width: 800px;
                padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    border: 3px solid #e9ecef;
                    margin: 0 auto;
                }
                
            .header {
                text-align: center;
                border-bottom: 3px solid #004225;
                    padding-bottom: 25px;
                margin-bottom: 30px;
            }
                
            .logo-container {
                    margin-bottom: 20px;
            }
                
            .logo {
                max-width: 120px;
                height: auto;
                    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            }
                
            .title {
                    font-size: 32px;
                    font-weight: 800;
                    color: #2c3e50;
                    margin-bottom: 8px;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    text-align: center;
                    overflow-wrap: break-word;
                    word-wrap: break-word;
                }
                
            .subtitle {
                    font-size: 18px;
                    color: #6c757d;
                    font-weight: 500;
                    text-align: center;
                    overflow-wrap: break-word;
                    word-wrap: break-word;
            }
                
            .content {
                margin-bottom: 30px;
            }
                
            .section {
                    margin-bottom: 30px;
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 10px;
                    border-left: 4px solid #004225;
                }
                
            .section-title {
                    font-size: 20px;
                    font-weight: 700;
                color: #004225;
                    margin-bottom: 15px;
                    border-bottom: 2px solid #dee2e6;
                    padding-bottom: 8px;
            }
                
            .info-row {
                display: flex;
                    margin-bottom: 12px;
                    align-items: center;
            }
                
            .label {
                    font-weight: 600;
                width: 150px;
                    color: #495057;
                    font-size: 14px;
            }
                
            .value {
                flex: 1;
                    color: #212529;
                    font-weight: 500;
                    text-align: left;
                    max-width: 300px;
                    overflow-wrap: break-word;
                    word-wrap: break-word;
            }
                
            .equipment-list {
                list-style: none;
                padding: 0;
                    margin: 0;
            }
                
            .equipment-list li {
                    padding: 8px 0;
                    border-bottom: 1px solid #dee2e6;
                    color: #495057;
                    font-weight: 500;
                }
                
                .equipment-list li:last-child {
                    border-bottom: none;
            }
                
            .footer {
                text-align: center;
                margin-top: 40px;
                    padding-top: 25px;
                    border-top: 3px solid #004225;
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 10px;
                }
                
            .status-approved {
                    background: linear-gradient(45deg, #d1e7dd, #c3e6cb);
                color: #0f5132;
                    padding: 15px 25px;
                    border-radius: 10px;
                text-align: center;
                    font-weight: 700;
                    margin: 25px 0;
                    border: 2px solid #28a745;
                    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
                }
                
            .certificate-number {
                    background: linear-gradient(45deg, #e9ecef, #f8f9fa);
                    padding: 15px;
                    border-radius: 10px;
                text-align: center;
                    font-family: "Courier New", monospace;
                    font-size: 16px;
                    color: #495057;
                    margin-bottom: 25px;
                    border: 2px solid #dee2e6;
                    font-weight: 600;
                }
                
            .loading {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                    background: rgba(0,0,0,0.9);
                color: white;
                    padding: 30px;
                    border-radius: 15px;
                z-index: 1000;
                display: none;
                    text-align: center;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.1);
                }
                
                .loading h3 {
                    margin-bottom: 15px;
                    color: #28a745;
                }
                
            .certificate-container {
                display: flex;
                justify-content: center;
                width: 100%;
            }
                
                .success-message {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #28a745;
                    color: white;
                    padding: 15px 25px;
                    border-radius: 10px;
                    z-index: 1001;
                    display: none;
                    animation: slideIn 0.3s ease;
                }
                
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                /* Mobile Responsive */
                @media (max-width: 768px) {
                    body {
                        padding: 10px;
                    }
                    
                    .preview-container {
                        padding: 20px;
                        border-radius: 15px;
                    }
                    
                    .preview-title {
                        font-size: 24px;
                    }
                    
                    .action-buttons {
                        flex-direction: column;
                        align-items: center;
                    }
                    
                    .btn {
                        width: 100%;
                        max-width: 300px;
                        padding: 18px 25px;
                    }
                    
                    .certificate {
                        padding: 25px;
                        border-radius: 12px;
                    }
                    
                    .title {
                        font-size: 24px;
                    }
                    
                    .subtitle {
                        font-size: 16px;
                    }
                    
                    .section {
                        padding: 15px;
                    }
                    
                    .info-row {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 5px;
                    }
                    
                    .label {
                        width: 100%;
                        font-size: 13px;
                    }
                    
                    .value {
                        font-size: 15px;
                    }
                }
                
                @media (max-width: 480px) {
                    .preview-title {
                        font-size: 20px;
                    }
                    
                    .certificate {
                        padding: 20px;
                    }
                    
                    .title {
                        font-size: 20px;
                    }
                    
                    .section-title {
                        font-size: 18px;
                    }
                }
        </style>
    </head>
    <body>
        <div class="loading" id="loading">
                <h3>🔄 Generating Certificate...</h3>
                <p>Please wait while we create your high-quality PNG certificate.</p>
                <div style="margin-top: 15px;">
                    <div style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                </div>
            </div>
            
            <div class="success-message" id="successMessage">
                ✅ Certificate downloaded successfully!
        </div>
        
        <div class="preview-container">
            <div class="preview-header">
                <div class="preview-title">📄 Booking Certificate Preview</div>
                <div class="preview-subtitle">Review your certificate before downloading</div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="downloadCertificate()">
                    ⬇️ Download Certificate
                </button>
                <button class="btn btn-secondary" onclick="window.close()">
                    ❌ Close
                </button>
            </div>
            
            <div class="certificate-container">
                <div class="certificate" id="certificate">
                    <div class="header">
                        <div class="logo-container">
                            <img src="../images/logo.png" alt="PGOM Logo" class="logo">
                        </div>
                        <div class="title">BOOKING CERTIFICATE</div>
                        <div class="subtitle">Official Confirmation of Facility Reservation</div>
                    </div>

                    <div class="certificate-number">
                        Certificate No: BK-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($booking['created_at'])) . '
                    </div>

                    <div class="status-approved">
                        ✓ BOOKING APPROVED
                    </div>

                    <div class="content">
                        <div class="section">
                                <div class="section-title">📅 Booking Information</div>
                            <div class="info-row">
                                <div class="label">Facility:</div>
                                <div class="value">' . htmlspecialchars($booking['facility_name']) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Date:</div>
                                <div class="value">' . date('F d, Y (l)', strtotime($booking['start_time'])) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Time:</div>
                                <div class="value">' . date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Duration:</div>
                                <div class="value">' . round((strtotime($booking['end_time']) - strtotime($booking['start_time'])) / 3600, 1) . ' hours</div>
                            </div>
                        </div>

                        <div class="section">
                                <div class="section-title">👤 Requestor Information</div>
                            <div class="info-row">
                                <div class="label">Name:</div>
                                <div class="value">' . htmlspecialchars($booking['user_name'] ?: 'Not provided') . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Email:</div>
                                <div class="value">' . htmlspecialchars($booking['email'] ?: 'Not provided') . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Phone Number:</div>
                                <div class="value">' . htmlspecialchars($booking['phone_number'] ?: 'Not provided') . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Address:</div>
                                <div class="value">' . htmlspecialchars($booking['address'] ?: 'Not provided') . '</div>
                            </div>
                        </div>';

    if (!empty($equipment)) {
        $html .= '
                        <div class="section">
                                <div class="section-title">🔧 Requested Equipment</div>
                            <ul class="equipment-list">';
        foreach ($equipment as $item) {
            $html .= '<li>• ' . htmlspecialchars($item['name']) . ' (Qty: ' . $item['quantity'] . ')</li>';
        }
        $html .= '</ul>
                        </div>';
    }

    $html .= '
                        <div class="section">
                                <div class="section-title">📋 Booking Details</div>
                            <div class="info-row">
                                <div class="label">Booking ID:</div>
                                <div class="value">' . $booking['id'] . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Date Booked:</div>
                                <div class="value">' . date('F d, Y h:i A', strtotime($booking['created_at'])) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="label">Last Updated:</div>
                                <div class="value">' . date('F d, Y h:i A', strtotime($booking['updated_at'] ?: $booking['created_at'])) . '</div>
                            </div>
                        </div>
                    </div>

                    <div class="footer">
                        <p><strong>This certificate serves as official confirmation of your approved facility booking.</strong></p>
                        <p>Please present this document when accessing the facility.</p>
                            <p style="margin-top: 20px; font-size: 12px; color: #6c757d;">
                            Generated on: ' . date('F d, Y h:i A') . '<br>
                            PGOM Facilities Booking System
                        </p>
                    </div>
                </div>
            </div>
        </div>
            
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        
        <script>
            function downloadCertificate() {
                    const certificateElement = document.getElementById("certificate");
                    const loadingElement = document.getElementById("loading");

                    // To ensure consistent, high-quality output, we temporarily force the element\'s width
                    // to our desired output size (800px) before rendering the canvas.
                    const originalStyles = certificateElement.style.cssText;
                    certificateElement.style.width = "800px";
                    certificateElement.style.boxSizing = "border-box";

                // Show loading indicator
                    loadingElement.style.display = "block";
                
                    // Wait a bit for the browser to apply styles and load resources
                setTimeout(function() {
                        html2canvas(certificateElement, {
                            scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: "#ffffff",
                            logging: false
                    }).then(function(canvas) {
                        canvas.toBlob(function(blob) {
                            const url = URL.createObjectURL(blob);
                            const link = document.createElement("a");
                            link.href = url;
                                link.download = "PGOM_Booking_Certificate_' . $booking_id . '_' . date('Y-m-d') . '.png";
                                
                                // For mobile devices, opening in a new tab is a reliable way for users to save the image.
                                if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                                    link.target = "_blank";
                                    link.click();
                                } else {
                                    // Desktop browsers can handle direct download.
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                                }
                            
                                setTimeout(() => URL.revokeObjectURL(url), 1000);
                            
                                const successMsg = document.getElementById("successMessage");
                                successMsg.style.display = "block";
                                setTimeout(() => successMsg.style.display = "none", 3000);
                            
                            }, "image/png", 0.95); // High quality PNG
                    }).catch(function(error) {
                        console.error("Error generating certificate:", error);
                            loadingElement.innerHTML = `<h3>❌ Error</h3><p>Failed to generate certificate. Please try again.</p><button onclick="downloadCertificate()" style="margin-top: 10px; padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Retry</button>`;
                        }).finally(function() {
                            // Restore original styles and hide loading indicator
                            certificateElement.style.cssText = originalStyles;
                            loadingElement.style.display = "none";
                        });
                    }, 1500);
                }
                
                // Auto-focus on download button for better UX
                window.onload = function() {
                    // Preload the logo image
                    const logo = new Image();
                    logo.src = "../images/logo.png";
                    logo.onload = function() {
                        console.log("Logo loaded successfully");
                    };
                };
        </script>
    </body>
    </html>';

    // Set headers for HTML content
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output the HTML
    echo $html;

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 