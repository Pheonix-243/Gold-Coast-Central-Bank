<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Hero Slider</title>
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    /* We'll put CSS here */
    /* Variables & Base Styles */
:root {
  --primary: #1a73e8;
  --light: #fff;
  --dark: #111;
  --font: 'Inter', sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:var(--font);color:var(--light);}
a{text-decoration:none;}

/* 1. Navbar */
.navbar {
  position: fixed; top:0; left:0;
  width:100%; background:rgba(17,17,17,0.7);
  display:flex; justify-content:space-between;
  align-items:center; padding:1rem 2rem;
  z-index:1000; backdrop-filter: blur(10px);
}
.navbar .logo{font-weight:700;font-size:1.5rem;}
.navbar .nav-links a{
  margin-left:1.5rem;color:var(--light);font-weight:500;
}
.hamburger {
  display:none; flex-direction:column; cursor:pointer;
}
.hamburger span{
  height:3px;width:25px;
  background: var(--light);
  margin-bottom:5px;
  border-radius:5px;
}

/* 2. Hero */
.hero { height:100vh; position:relative; overflow:hidden;}
.slider { height:100%; position:relative;}
.slide {
  width:100%; height:100%; position:absolute;
  top:0; left:100%; background-size:cover;
  background-position:center; opacity:0;
  transition: opacity 1s ease, left 0s 1s;
}
.slide.active {
  left:0; opacity:1;
  transition: opacity 1s ease;
}
.slide-content {
  position:absolute; bottom:20%;
  left:10%; color:var(--light);
  max-width:500px;
  transform: translateY(20px);
  opacity:0;
  animation: fadeInUp 1s forwards 0.5s;
}
@keyframes fadeInUp {
  to {opacity:1; transform: translateY(0);}
}
.btn {
  display:inline-block;
  margin-top:1rem;
  padding:0.75rem 1.5rem;
  background: var(--primary);
  border:none;
  color:var(--light);
  border-radius:4px;
  font-weight:600;
  transition: background 0.3s;
}
.btn:hover { background: #1669c1; }

/* 3. Controls & Dots */
.controls span {
  position:absolute; top:50%; transform: translateY(-50%);
  color:var(--light); font-size:2rem; cursor:pointer;
  background:rgba(0,0,0,0.3); padding:0.5rem; border-radius:50%;
  user-select:none;
}
.controls .prev { left:2%; }
.controls .next { right:2%; }
.dots {
  position:absolute; bottom:5%; left:50%;
  transform:translateX(-50%);
}
.dot {
  height:12px;width:12px;
  background:rgba(255,255,255,0.5);
  margin:0 6px; border-radius:50%;
  display:inline-block;
  cursor:pointer;
}
.dot.active { background:var(--primary); }

/* 4. Scroll Down Indicator */
.scroll-down {
  position:absolute; bottom:2rem; left:50%;
  transform:translateX(-50%);
}
.scroll-down span {
  display:block; width:2px; height:2rem;
  background:var(--light); border-radius:1px;
  position:relative;
  animation: scroll 2s infinite;
}
@keyframes scroll {
  0%,20%{opacity:1; transform:translateY(0);}
  80%,100%{opacity:0; transform:translateY(20px);}
}

/* 5. Responsive */
@media(max-width:800px){
  .navbar .nav-links { display:none; flex-direction:column; background:rgba(17,17,17,0.9); position:fixed; top:60px; right:0; width:200px; padding:1rem; }
  .navbar .nav-links.open { display:flex; }
  .hamburger { display:flex; }
}

  </style>
</head>
<body>
  <header class="navbar">
    <div class="logo">YourLogo</div>
    <nav class="nav-links">
      <a href="#">Home</a>
      <a href="#about">About</a>
      <a href="#services">Services</a>
      <a href="#contact">Contact</a>
    </nav>
    <div class="hamburger">
      <span></span><span></span><span></span>
    </div>
  </header>

  <section class="hero">
    <div class="slider">
      <div class="slide active" style="background-image: url('pic3.avif')">
        <div class="slide-content">
          <h1>Slide One Heading</h1>
          <p>Subheading text goes here.</p>
          <a href="#services" class="btn">Get Started</a>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic2.jpg')">
        <div class="slide-content">
          <h1>Slide Two Heading</h1>
          <p>Another subtitle for the second slide.</p>
          <a href="#contact" class="btn">Contact Us</a>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic1.jpg')">
        <div class="slide-content">
          <h1>Slide Three Heading</h1>
          <p>Explainer text for slide three.</p>
          <a href="#about" class="btn">Learn More</a>
        </div>
      </div>

      <div class="controls">
        <span class="prev">&#10094;</span>
        <span class="next">&#10095;</span>
      </div>
      <div class="dots">
        <span data-slide="0" class="dot active"></span>
        <span data-slide="1" class="dot"></span>
        <span data-slide="2" class="dot"></span>
      </div>

      <div class="scroll-down">
        <span></span>
      </div>
    </div>
  </section>

  <script>
    /* We'll put JS here */

    // Mobile nav toggle
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('open');
});

// Slider logic
const slides = document.querySelectorAll('.slide');
const prev = document.querySelector('.prev');
const next = document.querySelector('.next');
const dots = document.querySelectorAll('.dot');

let current = 0;
const total = slides.length;
let slideInterval = setInterval(nextSlide, 10000);

function goToSlide(n) {
  slides[current].classList.remove('active');
  dots[current].classList.remove('active');
  current = (n + total) % total;
  slides[current].classList.add('active');
  dots[current].classList.add('active');
}
function nextSlide() { goToSlide(current + 1); }
function prevSlide() { goToSlide(current - 1); }

// Control events
next.addEventListener('click', () => { nextSlide(); resetInterval(); });
prev.addEventListener('click', () => { prevSlide(); resetInterval(); });
dots.forEach(dot => {
  dot.addEventListener('click', e => {
    goToSlide(parseInt(e.target.getAttribute('data-slide')));
    resetInterval();
  });
});

// Reset auto-slide timer
function resetInterval(){
  clearInterval(slideInterval);
  slideInterval = setInterval(nextSlide, 10000);
}

  </script>
</body>
</html>











































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
        /* 2. Hero */
.hero { height:100vh; position:relative; overflow:hidden;}
.slider { height:100%; position:relative;}
.slide {
  width:100%; height:100%; position:absolute;
  top:0; left:100%; background-size:cover;
  background-position:center; opacity:0;
  transition: opacity 1s ease, left 0s 1s;
}
.slide.active {
  left:0; opacity:1;
  transition: opacity 1s ease;
}
.slide-content {
  position:absolute; bottom:20%;
  left:10%; color:var(--light);
  max-width:500px;
  transform: translateY(20px);
  opacity:0;
  animation: fadeInUp 1s forwards 0.5s;
}
@keyframes fadeInUp {
  to {opacity:1; transform: translateY(0);}
}
.btn {
  display:inline-block;
  margin-top:1rem;
  padding:0.75rem 1.5rem;
  background: var(--primary);
  border:none;
  color:var(--light);
  border-radius:4px;
  font-weight:600;
  transition: background 0.3s;
}
.btn:hover { background: #1669c1; }

/* 3. Controls & Dots */
.controls span {
  position:absolute; top:50%; transform: translateY(-50%);
  color:var(--light); font-size:2rem; cursor:pointer;
  background:rgba(0,0,0,0.3); padding:0.5rem; border-radius:50%;
  user-select:none;
}
.controls .prev { left:2%; }
.controls .next { right:2%; }
.dots {
  position:absolute; bottom:5%; left:50%;
  transform:translateX(-50%);
}
.dot {
  height:12px;width:12px;
  background:rgba(255,255,255,0.5);
  margin:0 6px; border-radius:50%;
  display:inline-block;
  cursor:pointer;
}
.dot.active { background:var(--primary); }

/* 4. Scroll Down Indicator */
.scroll-down {
  position:absolute; bottom:2rem; left:50%;
  transform:translateX(-50%);
}
.scroll-down span {
  display:block; width:2px; height:2rem;
  background:var(--light); border-radius:1px;
  position:relative;
  animation: scroll 2s infinite;
}
@keyframes scroll {
  0%,20%{opacity:1; transform:translateY(0);}
  80%,100%{opacity:0; transform:translateY(20px);}
}

/* 5. Responsive */
@media(max-width:800px){
  .navbar .nav-links { display:none; flex-direction:column; background:rgba(17,17,17,0.9); position:fixed; top:60px; right:0; width:200px; padding:1rem; }
  .navbar .nav-links.open { display:flex; }
  .hamburger { display:flex; }
}

    </style>
</head>
<body>
   

<!-- Hero Section -->
<section class="hero">
    <div class="slider">
      <div class="slide active" style="background-image: url('pic3.avif')">
        <div class="slide-content">
          <h1>Slide One Heading</h1>
          <p>Subheading text goes here.</p>
          <a href="#services" class="btn">Get Started</a>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic2.jpg')">
        <div class="slide-content">
          <h1>Slide Two Heading</h1>
          <p>Another subtitle for the second slide.</p>
          <a href="#contact" class="btn">Contact Us</a>
        </div>
      </div>
      <div class="slide" style="background-image: url('pic1.jpg')">
        <div class="slide-content">
          <h1>Slide Three Heading</h1>
          <p>Explainer text for slide three.</p>
          <a href="#about" class="btn">Learn More</a>
        </div>
      </div>

      <div class="controls">
        <span class="prev">&#10094;</span>
        <span class="next">&#10095;</span>
      </div>
      <div class="dots">
        <span data-slide="0" class="dot active"></span>
        <span data-slide="1" class="dot"></span>
        <span data-slide="2" class="dot"></span>
      </div>

      <div class="scroll-down">
        <span></span>
      </div>
    </div>
  </section>


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
    /* We'll put JS here */

    // Mobile nav toggle
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('open');
});

// Slider logic
const slides = document.querySelectorAll('.slide');
const prev = document.querySelector('.prev');
const next = document.querySelector('.next');
const dots = document.querySelectorAll('.dot');

let current = 0;
const total = slides.length;
let slideInterval = setInterval(nextSlide, 10000);

function goToSlide(n) {
  slides[current].classList.remove('active');
  dots[current].classList.remove('active');
  current = (n + total) % total;
  slides[current].classList.add('active');
  dots[current].classList.add('active');
}
function nextSlide() { goToSlide(current + 1); }
function prevSlide() { goToSlide(current - 1); }

// Control events
next.addEventListener('click', () => { nextSlide(); resetInterval(); });
prev.addEventListener('click', () => { prevSlide(); resetInterval(); });
dots.forEach(dot => {
  dot.addEventListener('click', e => {
    goToSlide(parseInt(e.target.getAttribute('data-slide')));
    resetInterval();
  });
});

// Reset auto-slide timer
function resetInterval(){
  clearInterval(slideInterval);
  slideInterval = setInterval(nextSlide, 10000);
}

  </script>
</body>
</html>
