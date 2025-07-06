<?php 
$page_title = 'Home';
include 'includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: sans-serif;
      overflow-x: hidden;
    }
    .hero-slider {
      position: relative;
      width: 100vw;
      height: 90vh;
      overflow: hidden;
      margin-top: -7px;
    }
    .slider-track {
      display: flex;
      width: 100%;
      height: 100%;
      transition: transform 1s ease-in-out;
    }
    .slide {
      min-width: 100vw;
      height: 100%;
      position: relative;
      background-size: cover;
      background-position: center;
    }
    .overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.4);
    }
    .slide-content h1 {
      font-size: 3rem;
      margin-bottom: 1rem;
    }
    .slide-content p {
      font-size: 1.2rem;
    }
    .contents {
        position: absolute; 
        bottom: 20%;
        left: 10%; 
        height: 100%;
        display: flex; 
        align-items: center; 
        justify-content: center;
        animation: fadeInUp 1s forwards 0.5s;
        transform: translateY(20px);
    }
    .slide-content {
        position: absolute; 
        bottom: 20%;
        left: 10%; 
        color: var(--light);
        width: 500px;
        background-color: rgba(0, 0, 0, 0.5);
        padding: 2rem;
        border-radius: 8px;
        transform: translateY(20px);
        opacity: 0;
        animation: fadeInUp 1s forwards 0.5s;
    }
    @keyframes fadeInUp {
        to {
            opacity: 1; 
            transform: translateY(0);
        }
    }
    @media (max-width: 768px) {
        .slide-content {
            left: 5%;
            right: 5%;
            max-width: none;
            bottom: 10%;
        }
        .section {
            padding: 3rem 0;
        }
    }
    .quick-login-container {
        position: fixed;
        top: 80px;
        right: 40px;
        z-index: 1050;
        animation: slideInQuickLogin 1s cubic-bezier(.68,-0.55,.27,1.55);
    }
    @media (max-width: 991px) {
         .quick-login-container {
            animation: none;
        } 
    }
    @keyframes slideInQuickLogin {
        from { opacity: 0; transform: translateY(-40px) scale(0.95);}
        to   { opacity: 1; transform: translateY(0) scale(1);}
    }
    .quick-login {
        border: none;
        background: #fff;
        box-shadow: 0 8px 32px rgba(44, 62, 80, 0.15), 0 1.5px 6px rgba(255, 215, 0, 0.10);
        min-width: 270px;
        max-width: 320px;
        padding: 0.5rem 0.5rem 0.5rem 0.5rem;
        transition: box-shadow 0.3s;
    }
    .quick-login:hover {
        box-shadow: 0 12px 40px rgba(44, 62, 80, 0.22), 0 2px 8px rgba(255, 215, 0, 0.15);
    }
    .quick-login .card-body {
        padding: 1.5rem 1.25rem 1.25rem 1.25rem;
    }
    .quick-login h6 {
        letter-spacing: 1px;
        font-size: 1.1rem;
        color: #bfa100;
        text-align: center;
        margin-bottom: 1.2rem;
        font-family: 'Montserrat', Arial, sans-serif;
    }
    .quick-login input.form-control {
        border: 1.5px solid #ffe066;
        background: #fff;
        font-size: 0.97rem;
        transition: border-color 0.2s;
    }
    .quick-login input.form-control:focus {
        border-color: #bfa100;
        box-shadow: 0 0 0 2px #ffe06655;
    }
    .quick-login .btn-primary {
        background: var(--navy-blue);
        border: none;
        color: #fff;
        font-weight: 700;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(191,161,0,0.10);
        transition: background 0.2s, color 0.2s;
        letter-spacing: 0.5px;
    }
    .quick-login .btn-primary:hover, .quick-login .btn-primary:focus {
        background: linear-gradient(90deg, #ffd700 0%, #bfa100 100%);
        color: #111;
    }
    .quick-login .quick-login-links {
        margin-top: 0.7rem;
        text-align: center;
    }
    .quick-login .quick-login-links a {
        color: var(--navy-blue);
        font-size: 0.92rem;
        margin: 0 0.4rem;
        text-decoration: none;
        transition: color 0.2s;
    }
    .quick-login .quick-login-links a:hover {
        color: #222;
        text-decoration: underline;
    }
    .quick-login .quick-login-icon {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 0.7rem;
    }
    .quick-login .quick-login-icon i {
        font-size: 2.1rem;
        color: #ffd700;
        filter: drop-shadow(0 2px 6px #ffe06688);
        animation: bounceLoginIcon 1.2s infinite alternate;
    }
    @keyframes bounceLoginIcon {
        from { transform: translateY(0);}
        to   { transform: translateY(-7px);}
    }
    </style>
</head>
<body>

<div class="quick-login-container">
  <div class="card quick-login shadow-lg">
    <div class="card-body">
      <div class="quick-login-icon">
        <i class="fas fa-unlock-alt"></i>
      </div>
      <h6 class="fw-bold">Client Login</h6>
      <form autocomplete="off" action="login.php" method="post">
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" name="username" placeholder="Username" required autocomplete="username">
        </div>
        <div class="mb-2">
          <input type="password" class="form-control form-control-sm" name="password" placeholder="Password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-sm btn-primary w-100">
          <i class="fas fa-sign-in-alt me-1"></i> Login
        </button>
      </form>
      <div class="quick-login-links mt-2">
        <a href="forgot-password.php"><i class="fas fa-key me-1"></i>Forgot?</a>
        <span>|</span>
        <a href="register.php"><i class="fas fa-user-plus me-1"></i>Sign Up</a>
      </div>
    </div>
  </div>
</div>

<div class="hero-slider" id="heroSlider">
    <div class="slider-track" id="sliderTrack">
      <div class="slide" style="background-image: url('pic1.jpg');">
        <div class="overlay"></div> 
        <div class="container contents">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content fade-in">
                    <h1>Africa's Premier Financial Institution</h1>
                    <p class="lead">Experience world-class banking excellence with unparalleled security, innovation, and trust. Join millions of global customers who rely on GCC Bank for their most important financial decisions.</p>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-gold btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Open an Account
                        </a>
                        <a href="#about" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-info-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center slide-in-right">
                    <div class="hero-stats row g-3">
                        <div class="col-4">
                            <div class="stats-card">
                                <span class="stats-number">2.5M+</span>
                                <span class="stats-label">Global Customers</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stats-card">
                                <span class="stats-number">$50B+</span>
                                <span class="stats-label">Assets Management</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stats-card">
                                <span class="stats-number">150+</span>
                                <span class="stats-label">Global Locations</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic2.jpg');">
         <div class="overlay"></div> 
        <div class="slide-content">
          <h1>Inspire. Build. Repeat.</h1>
          <p>Second slide, same greatness</p>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic3.avif');">
         <div class="overlay"></div> 
        <div class="slide-content">
          <h1>Forward Only, No Rewinds</h1>
          <p>This is how we slide</p>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic5.png');">
         <div class="overlay"></div> 
        <div class="slide-content">
          <h1>Forward Only, No Rewinds</h1>
          <p>This is how we slide</p>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic6.png');">
        <div class="overlay"></div>
      </div>
      <div class="slide" style="background-image: url('pic7.png');">
        <div class="overlay"></div>
      </div>
      <!-- Cloned First Slide for seamless forward loop -->
      <div class="slide" style="background-image: url('pic1.jpg');">
         <div class="overlay"></div> 
        <div class="slide-content">
          <h1>Welcome to Bright's World</h1>
          <p>This is the first slide (clone)</p>
        </div>
      </div>
    </div>
  </div>

<!-- About Section -->
<section id="about" class="section about-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 slide-in-left">
                <h2 class="section-title text-start">Our Heritage, Your Future</h2>
                <p class="mb-4">Gold Coast Central Bank stands as Africa's most trusted financial powerhouse, with a legacy spanning decades of unwavering excellence. Rooted in Ghana's golden heritage, we've expanded globally to serve millions across continents with unmatched financial expertise and innovation.</p>
                
                <p class="mb-4">Our mission transcends borders: to deliver world-class financial solutions that empower individuals, corporations, and nations to achieve unprecedented growth and prosperity in the global economy.</p>
                
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="service-icon me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Global Banking Authority</h6>
                                <small class="text-muted">Licensed across 45+ countries</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="service-icon me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Global Excellence Awards</h6>
                                <small class="text-muted">World's Top Bank 2024</small>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="#services" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i>Explore Our Services
                </a>
            </div>
            
            <div class="col-lg-6 fade-in">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="bg-white p-4 rounded-3 shadow-sm border-start border-5 border-warning">
                            <h5 class="text-navy mb-3">Our Values</h5>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-handshake text-gold fs-2 mb-2"></i>
                                        <h6>Trust</h6>
                                        <small class="text-muted">Building lasting relationships</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-lightbulb text-gold fs-2 mb-2"></i>
                                        <h6>Innovation</h6>
                                        <small class="text-muted">Leading financial technology</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-users text-gold fs-2 mb-2"></i>
                                        <h6>Community</h6>
                                        <small class="text-muted">Supporting local growth</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-star text-gold fs-2 mb-2"></i>
                                        <h6>Excellence</h6>
                                        <small class="text-muted">Delivering quality service</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">World-Class Financial Services</h2>
            <p class="section-subtitle">Elite banking solutions trusted by governments, corporations, and high-net-worth individuals across 6 continents. Experience banking without boundaries.</p>
            
            <div class="premium-badges mt-3">
                <span class="premium-badge"><i class="fas fa-star me-1"></i>Forbes Global 2000</span>
                <span class="premium-badge"><i class="fas fa-award me-1"></i>World's Safest Bank 2024</span>
                <span class="premium-badge"><i class="fas fa-globe me-1"></i>Operating in 150+ Countries</span>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6 fade-in">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5>Personal Banking</h5>
                    <p>Complete personal banking solutions including savings accounts, current accounts, fixed deposits, and personal loans with competitive interest rates.</p>
                    <ul class="list-unstyled mt-3">
                        <li><i class="fas fa-check text-success me-2"></i>Savings & Current Accounts</li>
                        <li><i class="fas fa-check text-success me-2"></i>Personal Loans</li>
                        <li><i class="fas fa-check text-success me-2"></i>Debit & Credit Cards</li>
                        <li><i class="fas fa-check text-success me-2"></i>Online Banking</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 fade-in">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h5>Business Banking</h5>
                    <p>Tailored banking solutions for businesses of all sizes, from SMEs to large corporations, with dedicated relationship managers and flexible terms.</p>
                    <ul class="list-unstyled mt-3">
                        <li><i class="fas fa-check text-success me-2"></i>Business Accounts</li>
                        <li><i class="fas fa-check text-success me-2"></i>Trade Finance</li>
                        <li><i class="fas fa-check text-success me-2"></i>Working Capital Loans</li>
                        <li><i class="fas fa-check text-success me-2"></i>Cash Management</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 fade-in">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h5>International Transfers</h5>
                    <p>Fast, secure, and cost-effective international money transfer services with competitive exchange rates and global reach.</p>
                    <ul class="list-unstyled mt-3">
                        <li><i class="fas fa-check text-success me-2"></i>Swift Transfers</li>
                        <li><i class="fas fa-check text-success me-2"></i>Remittance Services</li>
                        <li><i class="fas fa-check text-success me-2"></i>Foreign Exchange</li>
                        <li><i class="fas fa-check text-success me-2"></i>Trade Documentation</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 fade-in">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h5>Mobile Money Integration</h5>
                    <p>Seamless integration with popular mobile money platforms in Ghana, enabling easy transfers and payments through your mobile device.</p>
                    <ul class="list-unstyled mt-3">
                        <li><i class="fas fa-check text-success me-2"></i>MTN Mobile Money</li>
                        <li><i class="fas fa-check text-success me-2"></i>Vodafone Cash</li>
                        <li><i class="fas fa-check text-success me-2"></i>AirtelTigo Money</li>
                        <li><i class="fas fa-check text-success me-2"></i>Instant Transfers</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 fade-in">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h5>Digital Wallet</h5>
                    <p>Modern digital wallet solution for secure online payments, bill payments, and peer-to-peer transfers with advanced security features.</p>
                    <ul class="list-unstyled mt-3">
                        <li><i class="fas fa-check text-success me-2"></i>Digital Payments</li>
                        <li><i class="fas fa-check text-success me-2"></i>Bill Payments</li>
                        <li><i class="fas fa-check text-success me-2"></i>QR Code Payments</li>
                        <li><i class="fas fa-check text-success me-2"></i>Merchant Services</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 fade-in">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5>Investment Services</h5>
                    <p>Professional investment advisory services, treasury bills, bonds, and portfolio management to help grow your wealth securely.</p>
                    <ul class="list-unstyled mt-3">
                        <li><i class="fas fa-check text-success me-2"></i>Treasury Bills</li>
                        <li><i class="fas fa-check text-success me-2"></i>Government Bonds</li>
                        <li><i class="fas fa-check text-success me-2"></i>Portfolio Management</li>
                        <li><i class="fas fa-check text-success me-2"></i>Investment Advisory</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Security Section -->
<section class="security-section section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Military-Grade Security & Global Compliance</h2>
            <p class="section-subtitle">Protecting $50+ billion in global assets with quantum-resistant encryption and AI-powered fraud detection. Trusted by central banks and Fortune 500 companies worldwide.</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-3 col-md-6 fade-in">
                <div class="security-feature">
                    <div class="security-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h6 class="text-white mb-2">256-bit SSL Encryption</h6>
                    <p class="text-light mb-0">All data transmissions are protected with military-grade encryption</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 fade-in">
                <div class="security-feature">
                    <div class="security-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h6 class="text-white mb-2">KYC Compliance</h6>
                    <p class="text-light mb-0">Rigorous Know Your Customer procedures for account security</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 fade-in">
                <div class="security-feature">
                    <div class="security-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h6 class="text-white mb-2">Multi-Factor Authentication</h6>
                    <p class="text-light mb-0">Advanced authentication methods for secure account access</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 fade-in">
                <div class="security-feature">
                    <div class="security-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h6 class="text-white mb-2">Fraud Detection</h6>
                    <p class="text-light mb-0">24/7 monitoring and real-time fraud prevention systems</p>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-lg-8 mx-auto text-center">
                <div class="bg-white bg-opacity-10 rounded-3 p-4">
                    <h5 class="text-gold mb-3">Regulatory Compliance</h5>
                    <p class="text-light mb-0">GCC Bank is fully licensed and regulated by the Bank of Ghana, adhering to all local and international banking standards including AML/CFT regulations and Basel III requirements.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Why Global Leaders Choose GCC Bank</h2>
            <p class="section-subtitle">Discover what makes us the preferred banking partner for Fortune 500 companies, governments, and high-net-worth individuals worldwide.</p>
        </div>
        
        <div class="row g-4 align-items-center">
            <div class="col-lg-6 slide-in-left">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="service-icon me-4" style="width: 60px; height: 60px;">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div>
                                <h5 class="text-navy mb-2">Lightning Fast Service</h5>
                                <p class="text-muted mb-0">Experience instant account opening, real-time transfers, and 24/7 customer support. Our digital-first approach ensures you can bank anytime, anywhere.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="service-icon me-4" style="width: 60px; height: 60px;">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div>
                                <h5 class="text-navy mb-2">Customer-Centric Approach</h5>
                                <p class="text-muted mb-0">Our dedicated relationship managers provide personalized service, ensuring your banking experience exceeds expectations every time.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="service-icon me-4" style="width: 60px; height: 60px;">
                                <i class="fas fa-mobile"></i>
                            </div>
                            <div>
                                <h5 class="text-navy mb-2">Mobile-First Banking</h5>
                                <p class="text-muted mb-0">Our award-winning mobile app puts the power of banking in your hands with intuitive design and cutting-edge features.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="service-icon me-4" style="width: 60px; height: 60px;">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h5 class="text-navy mb-2">Global Presence</h5>
                                <p class="text-muted mb-0">With 150+ locations across 6 continents and the world's largest banking network, we're always within reach when you need us most.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 slide-in-right">
                <div class="bg-light-gray p-5 rounded-3">
                    <div class="text-center mb-4">
                        <h4 class="text-navy mb-3">Join Our Growing Community</h4>
                        <p class="text-muted">Become part of Ghana's most innovative banking community</p>
                    </div>
                    
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="bg-white p-3 rounded">
                                <h3 class="text-gold mb-1">99.9%</h3>
                                <small class="text-muted">Uptime Guarantee</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white p-3 rounded">
                                <h3 class="text-gold mb-1">&lt;2min</h3>
                                <small class="text-muted">Average Response Time</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white p-3 rounded">
                                <h3 class="text-gold mb-1">4.8★</h3>
                                <small class="text-muted">Customer Rating</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white p-3 rounded">
                                <h3 class="text-gold mb-1">24/7</h3>
                                <small class="text-muted">Support Available</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="register.php" class="btn btn-primary btn-lg">Start Banking Today</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="section about-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Trusted by Global Leaders</h2>
            <p class="section-subtitle">Hear from Fortune 500 CEOs, government officials, and industry leaders who trust GCC Bank with their most critical financial decisions.</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 fade-in">
                <div class="testimonial-card">
                    <p class="testimonial-text">"GCC Bank's institutional banking solutions have been instrumental in our African expansion. Their cross-border expertise and regulatory compliance capabilities are unmatched in the region. They truly understand global commerce."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">MT</div>
                        <div class="author-info">
                            <h6>Michael Thompson</h6>
                            <small>CEO, TransGlobal Mining Corp</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 fade-in">
                <div class="testimonial-card">
                    <p class="testimonial-text">"As Ghana's Minister of Finance, I've worked with many international banks. GCC Bank's commitment to transparency, compliance, and economic development makes them our preferred partner for sovereign transactions and development projects."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">KO</div>
                        <div class="author-info">
                            <h6>Dr. Kwaku Ofori</h6>
                            <small>Former Minister of Finance, Ghana</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 fade-in">
                <div class="testimonial-card">
                    <p class="testimonial-text">"GCC Bank's wealth management division has consistently delivered superior returns for our $2B family office. Their global reach, sophisticated investment products, and white-glove service rival any Swiss private bank."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">SA</div>
                        <div class="author-info">
                            <h6>Sarah Al-Rashid</h6>
                            <small>CIO, Meridian Family Office</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <p class="text-muted mb-3">Trusted by 2.5 million global customers</p>
            <div class="d-flex justify-content-center align-items-center">
                <div class="me-3">
                    <span class="text-gold fs-4">★★★★★</span>
                </div>
                <div>
                    <h5 class="mb-0 text-navy">4.8 out of 5</h5>
                    <small class="text-muted">Based on 2,500+ reviews</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="section contact-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">Have questions? We're here to help. Reach out to our customer service team or visit one of our branches.</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="contact-form fade-in">
                    <h4 class="text-navy mb-4">Send us a Message</h4>
                    <form id="contactForm" class="needs-validation" novalidate>
                        <?= csrf_token_input() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="fullName" name="full_name" placeholder="Full Name" required>
                                    <label for="fullName">Full Name *</label>
                                    <div class="invalid-feedback">Please provide your full name.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required>
                                    <label for="email">Email Address *</label>
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" required>
                                    <label for="phone">Phone Number *</label>
                                    <div class="invalid-feedback">Please provide a valid Ghana phone number.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="subject" name="subject" required>
                                        <option value="">Select Subject</option>
                                        <option value="account_inquiry">Account Inquiry</option>
                                        <option value="loan_application">Loan Application</option>
                                        <option value="investment_services">Investment Services</option>
                                        <option value="technical_support">Technical Support</option>
                                        <option value="complaint">Complaint</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <label for="subject">Subject *</label>
                                    <div class="invalid-feedback">Please select a subject.</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="message" name="message" placeholder="Your Message" style="height: 120px" required></textarea>
                                    <label for="message">Your Message *</label>
                                    <div class="invalid-feedback">Please provide your message.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="contact-info-card slide-in-right">
                    <h4 class="text-gold mb-4">Contact Information</h4>
                    
                    <div class="contact-item">
                        <div class="contact-item-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1">Head Office</h6>
                            <p class="text-light mb-0">Independence Avenue<br>Accra, Ghana</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-item-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1">Phone</h6>
                            <p class="text-light mb-0">+233 30 123 4567<br>+233 24 567 8901</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-item-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1">Email</h6>
                            <p class="text-light mb-0">info@gccbank.com.gh<br>support@gccbank.com.gh</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-item-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1">Business Hours</h6>
                            <p class="text-light mb-0">Mon - Fri: 8:00 AM - 5:00 PM<br>Sat: 9:00 AM - 2:00 PM</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-top border-secondary">
                        <h6 class="text-gold mb-3">Emergency Support</h6>
                        <p class="text-light mb-2">24/7 Customer Hotline:</p>
                        <h5 class="text-white">+233 30 911 911</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Optimized slider implementation
    const sliderTrack = document.getElementById('sliderTrack');
    const slides = document.querySelectorAll('.slide');
    const slideCount = slides.length;
    const transitionDuration = 1000; // ms
    const slideInterval = 6000; // ms
    
    // Clone the first slide and append to end for seamless looping
    const firstSlide = slides[0].cloneNode(true);
    sliderTrack.appendChild(firstSlide);
    
    let currentIndex = 0;
    let isTransitioning = false;
    let sliderInterval;
    
    function goToSlide(index) {
        if (isTransitioning) return;
        
        isTransitioning = true;
        currentIndex = index;
        
        sliderTrack.style.transition = `transform ${transitionDuration}ms ease-in-out`;
        sliderTrack.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // After transition completes, check if we need to reset position
        setTimeout(() => {
            isTransitioning = false;
            
            // If we're at the cloned first slide, instantly jump to the real first slide
            if (currentIndex === slideCount) {
                sliderTrack.style.transition = 'none';
                sliderTrack.style.transform = 'translateX(0)';
                currentIndex = 0;
                
                // Force reflow
                void sliderTrack.offsetWidth;
            }
        }, transitionDuration);
    }
    
    function nextSlide() {
        goToSlide(currentIndex + 1);
    }
    
    function startSlider() {
        sliderInterval = setInterval(nextSlide, slideInterval);
    }
    
    function stopSlider() {
        clearInterval(sliderInterval);
    }
    
    // Handle visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSlider();
        } else {
            startSlider();
        }
    });
    
    // Initialize
    startSlider();
    
    // Pause on hover
  
    
    // Preload images for smoother transitions
    const imageUrls = ['pic1.jpg', 'pic2.jpg', 'pic3.avif', 'pic5.png', 'pic6.png', 'pic7.png'];
    imageUrls.forEach(url => {
        const img = new Image();
        img.src = url;
    });
});
</script>
</body>
</html>