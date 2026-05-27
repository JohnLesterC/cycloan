<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notifications - CYCLOAN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #1b5e20;
            --secondary: #2e7d32;
            --dark: #1a3c34;
            --light: #f8fafc;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: "Poppins", sans-serif;
            background: #e2e8f0;
            margin: 0;
            padding: 20px;
        }

        .test-container {
            background: var(--light);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: var(--primary);
            margin-bottom: 30px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .btn {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="test-container">
        <div class="header">
            <h1><i class="fas fa-bell"></i> Notifications Test Page</h1>
            <p>Testing if basic elements render properly</p>
        </div>

        <div class="status-grid">
            <div class="status-card">
                <h3>CSS Loading</h3>
                <p>✅ Working</p>
            </div>
            <div class="status-card">
                <h3>Font Awesome</h3>
                <p><i class="fas fa-check"></i> Working</p>
            </div>
            <div class="status-card">
                <h3>Google Fonts</h3>
                <p>✅ Poppins Loaded</p>
            </div>
        </div>

        <div style="text-align: center;">
            <button class="btn" onclick="testAction()">
                <i class="fas fa-test"></i> Test Button
            </button>
            <button class="btn" onclick="window.location.href='notifications.php'">
                <i class="fas fa-arrow-right"></i> Go to Real Notifications
            </button>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-radius: 8px;">
            <h3>Debug Information:</h3>
            <p><strong>Current URL:</strong> <span id="currentUrl"></span></p>
            <p><strong>User Agent:</strong> <span id="userAgent"></span></p>
            <p><strong>Screen Size:</strong> <span id="screenSize"></span></p>
            <p><strong>CSS Variables Test:</strong>
                <span style="color: var(--primary); font-weight: bold;">Primary Color</span> |
                <span style="color: var(--secondary); font-weight: bold;">Secondary Color</span>
            </p>
        </div>
    </div>

    <script>
        function testAction() {
            alert('Test button works! CSS and JS are loading properly.');
        }

        document.getElementById('currentUrl').textContent = window.location.href;
        document.getElementById('userAgent').textContent = navigator.userAgent.substring(0, 50) + '...';
        document.getElementById('screenSize').textContent = window.innerWidth + 'x' + window.innerHeight;

        console.log('Test page loaded successfully');
    </script>
</body>

</html>