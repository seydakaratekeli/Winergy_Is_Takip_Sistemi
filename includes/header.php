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
        
        .top-header {
            background: #ffffff;
            padding: 12px 0;
            border-bottom: 2px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .navbar-logo {
            max-height: 45px;
            transition: all 0.3s ease;
        }
        
        .navbar-logo:hover {
            transform: scale(1.03);
        }
        
        .contact-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .contact-phone {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .contact-phone:hover {
            color: #14b8a6;
            transform: translateX(3px);
        }
        
        .contact-phone .phone-icon {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
        }
        
        .contact-phone .phone-text {
            display: flex;
            flex-direction: column;
            line-height: 1.3;
        }
        
        .contact-phone .phone-label {
            font-size: 1.2rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .contact-phone .phone-number {
            font-size: 1.6rem;
            color: #14b8a6;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        
        .social-links {
            display: flex;
            gap: 10px;
        }
        
        .social-links a {
            width: 36px;
            height: 36px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.4rem;
        }
        
        .social-links a:hover {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.4);
        }
        
        .main-navbar {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%) !important;
            padding: 0;
            box-shadow: 0 4px 16px rgba(20, 184, 166, 0.3);
        }
        
        .main-navbar .navbar-nav {
            gap: 5px;
        }
        
        .main-navbar .nav-link {
            color: white !important;
            font-weight: 600;
            padding: 20px 24px !important;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-size: 1.5rem;
            white-space: nowrap;
        }
        
        .main-navbar .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            border-bottom-color: white;
            transform: translateY(-2px);
        }
        
        .main-navbar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-bottom-color: white;
            font-weight: 700;
        }
        
        .main-navbar .input-group {
            min-width: 280px;
        }
        
        .main-navbar .input-group .form-control {
            font-size: 1.4rem;
            padding: 10px 16px;
        }
        
        .main-navbar .btn {
            font-size: 1.5rem;
            padding: 10px 20px;
        }
        
        .main-navbar .dropdown-menu {
            font-size: 1.4rem;
            min-width: 220px;
        }
        
        /* Responsive Navbar */
        @media (max-width: 991px) {
            .main-navbar .nav-link {
                padding: 15px 20px !important;
                font-size: 1.6rem;
            }
            
            .main-navbar .input-group {
                min-width: 100%;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>

<!-- Top Header - Logo, Telefon, Sosyal Medya -->
<div class="top-header">
    <div class="container">
        <div class="row align-items-center justify-content-between g-3">
            <div class="col-lg-3 col-md-4 col-6">
                <a href="https://winergytechnologies.com" target="_blank" rel="noopener noreferrer">
                    <img src="assets/images/winergy-logo.png" alt="Winergy Technologies" class="navbar-logo">
                </a>
            </div>
            <div class="col-lg-9 col-md-8 col-12">
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
                        <a href="https://x.com/winergyT" target="_blank" title="Twitter">
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
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin-logs.php' ? 'active' : ''; ?>" href="admin-logs.php">
                        <i class="bi bi-journal-text me-1"></i> Sistem Logları
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'toplu-import.php' ? 'active' : ''; ?>" href="toplu-import.php">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Toplu Import
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <!-- Global Arama -->
            <form action="arama.php" method="GET" class="d-flex ms-auto me-2 my-2">
                <div class="input-group">
                    <input type="text" name="q" class="form-control bg-white" placeholder="İş, müşteri ara..." 
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" required>
                    <button class="btn btn-light" type="submit" title="Ara">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="arama.php" class="btn btn-outline-light" title="Gelişmiş Arama">
                        <i class="bi bi-sliders"></i>
                    </a>
                </div>
            </form>
            
            <ul class="navbar-nav ms-2">
                <li class="nav-item dropdown">
                    <button class="btn btn-outline-light dropdown-toggle my-2 px-3" type="button" data-bs-toggle="dropdown" style="border-radius: 8px; font-size: 1.5rem; min-width: 160px;">
                        <i class="bi bi-person-circle me-1"></i> <?php echo mb_substr($_SESSION['user_name'] ?? 'Kullanıcı', 0, 15); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                        <li><span class="dropdown-item-text text-muted fw-bold" style="font-size: 1.3rem;">
                            <i class="bi bi-shield-check me-2"></i>
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
                        <li><a class="dropdown-item text-danger fw-bold" href="logout.php" style="font-size: 1.4rem;">
                            <i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-wrapper">
<div class="container">