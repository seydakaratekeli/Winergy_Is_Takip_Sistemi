<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winergy Technologies - İş Takip Sistemi</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
    
    <style>
        /* Winergy Web Sitesi Benzeri Header */
        .top-header {
            background: #ffffff;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .contact-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .contact-phone {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
            text-decoration: none;
            font-weight: 600;
        }
        
        .contact-phone:hover {
            color: #14b8a6;
        }
        
        .contact-phone .phone-icon {
            background: #14b8a6;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .contact-phone .phone-text {
            display: flex;
            flex-direction: column;
        }
        
        .contact-phone .phone-label {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .contact-phone .phone-number {
            font-size: 1rem;
            color: #14b8a6;
            font-weight: 700;
        }
        
        .social-links {
            display: flex;
            gap: 10px;
        }
        
        .social-links a {
            width: 35px;
            height: 35px;
            background: #f3f4f6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: #14b8a6;
            color: white;
            transform: translateY(-2px);
        }
        
        .main-navbar {
            background: #14b8a6 !important;
            padding: 0;
        }
        
        .main-navbar .navbar-nav {
            width: 100%;
        }
        
        .main-navbar .nav-link {
            color: white !important;
            font-weight: 600;
            padding: 18px 20px !important;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .main-navbar .nav-link:hover,
        .main-navbar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            border-bottom-color: white;
        }
        
        .navbar-logo {
            max-height: 50px;
        }
    </style>
</head>
<body>

<!-- Top Header - Logo, Telefon, Sosyal Medya -->
<div class="top-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-4">
                <a href="index.php">
                    <img src="assets/images/winergy-logo.png" alt="Winergy Technologies" class="navbar-logo">
                </a>
            </div>
            <div class="col-md-8">
                <div class="d-flex justify-content-end align-items-center contact-info">
                    <a href="tel:03123956828" class="contact-phone">
                        <div class="phone-icon">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div class="phone-text">
                            <span class="phone-label">Hemen Bize Ulaşın</span>
                            <span class="phone-number">0312 395 68 28</span>
                        </div>
                    </a>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/company/winergy-tech" target="_blank" title="LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <a href="https://www.instagram.com/winergytech/" target="_blank" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="https://twitter.com/winergytech" target="_blank" title="Twitter">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark main-navbar mb-4">
    <div class="container">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-house-door-fill me-1"></i> Anasayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'musteriler.php' ? 'active' : ''; ?>" href="musteriler.php">
                        <i class="bi bi-building me-1"></i> Müşteriler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'raporlar.php' ? 'active' : ''; ?>" href="raporlar.php">
                        <i class="bi bi-bar-chart-fill me-1"></i> Raporlar
                    </a>
                </li>
                <?php if($_SESSION['user_role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['kullanicilar.php', 'kullanici-ekle.php', 'kullanici-duzenle.php']) ? 'active' : ''; ?>" href="kullanicilar.php">
                        <i class="bi bi-people-fill me-1"></i> Kullanıcılar
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <!-- Global Arama -->
            <form action="arama.php" method="GET" class="d-flex ms-auto me-3 my-2" style="min-width: 300px;">
                <div class="input-group">
                    <input type="text" name="q" class="form-control bg-white" placeholder="İş, müşteri, not ara..." 
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" required>
                    <button class="btn btn-light" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="arama.php" class="btn btn-outline-light" title="Gelişmiş Arama">
                        <i class="bi bi-sliders"></i>
                    </a>
                </div>
            </form>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="btn btn-light text-dark fw-bold my-2" href="is-ekle.php" style="border-radius: 6px;">
                        <i class="bi bi-plus-circle-fill me-1"></i> Yeni İş Ekle
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <button class="btn btn-outline-light dropdown-toggle ms-2 my-2" type="button" data-bs-toggle="dropdown" style="border-radius: 6px;">
                        <i class="bi bi-person-circle me-1"></i> <?php echo $_SESSION['user_name'] ?? 'Kullanıcı'; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small">
                            <?php 
                            $role_tr = [
                                'admin' => 'Yönetici',
                                'operasyon' => 'Operasyon',
                                'danisman' => 'Danışman'
                            ];
                            echo $role_tr[$_SESSION['user_role']] ?? $_SESSION['user_role'];
                            ?>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">