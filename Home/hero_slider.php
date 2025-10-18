<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Banking Solutions</title>
    <!-- Preload first image for better performance -->
    <link rel="preload" href="./assets/images/pic1.jpg" as="image" fetchpriority="high">
    
    <style>
       /* ===== RESET & BASE STYLES ===== */
* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
}

html, body {
    height: 100%;
}

body {
    font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
    overflow-x: hidden;
    line-height: 1.6;
    font-weight: 400;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    display: flex;
    flex-direction: column;
}

/* ===== HERO SLIDER CONTAINER ===== */
/* .hero-slider {
    background: linear-gradient(135deg, #0f1419 0%, #1a2332 50%, #2c3e50 100%);
    position: relative;
    width: 100%;
    flex: 1;
    min-height: 500px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05);
    margin: 0;
} */

.hero-slider {
    background: linear-gradient(135deg, #0f1419 0%, #1a2332 50%, #2c3e50 100%);
    position: relative;
    width: 100%;
    flex: 1 0 auto;
    min-height: 0;
    height: calc(100vh - 40px); /* Adjusted to account for announcement bar */
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05);
    margin: 0;
}


.hero-slider::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(ellipse at center, rgba(255,255,255,0.02) 0%, transparent 70%);
    pointer-events: none;
    z-index: 1;
}

.hero-slider .slider-track {
    display: flex;
    height: 100%;
    width: 100%;
    transition: transform 1400ms cubic-bezier(0.25, 0.46, 0.45, 0.94);
    will-change: transform;
}

/* ===== SLIDE STYLES ===== */
.hero-slider .slide {
    min-width: 100%;
    height: 100%;
    position: relative;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 0 8%;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    /* Prevent transform issues that cause peeking */
    transform: translateX(0) scale(1);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.hero-slider .slide::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(15, 32, 60, 0.9) 0%, 
        rgba(25, 45, 75, 0.8) 30%,
        rgba(35, 55, 85, 0.75) 60%,
        rgba(45, 65, 95, 0.7) 100%);
    z-index: 1;
}

.hero-slider .slide::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(ellipse at 30% 50%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
    z-index: 2;
}

.hero-slider .slide .btn:hover::before {
    left: 100%;
}

.hero-slider .slide-content .btn:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 
        0 16px 45px rgba(37, 99, 235, 0.5),
        0 6px 20px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%);
}

.hero-slider .dot.active {
    background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
    transform: scale(1.15);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.6), 0 0 5px rgba(255, 255, 255, 0.3);
}

.hero-slider .dot.active::before {
    content: '';
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.1); }
}

.hero-slider .slide.loaded {
    filter: brightness(0.92) saturate(1.1);
}

.hero-slider .slide.active {
    filter: brightness(1) saturate(1.2) drop-shadow(0 0 40px #fff2);
}

/* ===== SLIDE CONTENT ===== */
.hero-slider .slide-content {
    position: relative;
    z-index: 3;
    max-width: 700px;
    color: white;
    text-align: left;
    opacity: 0;
    transform: translateY(50px) scale(0.98);
    transition: all 1000ms cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.hero-slider .slide.active .slide-content {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.hero-slider .slide-content h4 {
    font-size: clamp(2.4rem, 5vw, 4.2rem);
    font-weight: 800;
    line-height: 1.05;
    margin-bottom: 1.5rem;
    text-shadow: 0 6px 30px rgba(0,0,0,0.6), 0 2px 8px rgba(0,0,0,0.3);
    letter-spacing: -0.03em;
    background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    position: relative;
}

.hero-slider .slide-content h4::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 60px;
    height: 3px;
    background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 2px;
}

.hero-slider .slide p {
    font-size: clamp(1rem, 2vw, 1.2rem);
    font-weight: 400;
    line-height: 1.65;
    margin-bottom: 32px;
    color: rgba(255, 255, 255, 0.95);
    text-shadow: 0 2px 12px rgba(0,0,0,0.5);
    max-width: 580px;
    letter-spacing: 0.01em;
    opacity: 0.95;
}

/* ===== BUTTON STYLES ===== */
.hero-slider .slide-content .btn {
    display: inline-block;
    padding: 16px 32px;
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 50%, #1e3a8a 100%);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.05rem;
    letter-spacing: 0.3px;
    transition: all 400ms cubic-bezier(0.25, 0.46, 0.45, 0.94);
    box-shadow: 
        0 12px 35px rgba(37, 99, 235, 0.4),
        0 4px 15px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255,255,255,0.12);
    backdrop-filter: blur(15px);
    position: relative;
    overflow: hidden;
}

.hero-slider .slide .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transition: left 500ms ease;
}

/* ===== NAVIGATION DOTS ===== */
.hero-slider .slider-dots {
    position: absolute;
    bottom: 32px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 14px;
    z-index: 10;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(20px);
    border-radius: 30px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.hero-slider .dot {
    width: 14px; height: 14px;
    border-radius: 50%;
    background: rgba(255,255,255,0.45);
    cursor: pointer;
    box-shadow: 0 2px 8px #0002;
    transition: all 0.5s cubic-bezier(0.16, 0.77, 0.22, 0.99);
    position: relative;
    will-change: transform, background;
}

/* ===== ARROWS ===== */
.hero-slider .slider-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    font-size: 24px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 350ms cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.75;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.hero-slider .slider-arrow:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-50%) scale(1.08);
    opacity: 1;
    box-shadow: 0 12px 35px rgba(0,0,0,0.35);
    border-color: rgba(255, 255, 255, 0.25);
}

.hero-slider .slider-arrow.left { left: 32px; }
.hero-slider .slider-arrow.right { right: 32px; }

/* ===== SUBTLE ANNOUNCEMENT BAR ===== */
/* .announcement-bar {
    background: rgba(15, 20, 25, 0.95);
    color: rgba(255, 255, 255, 0.85);
    border-bottom: 1px solid rgba(255, 215, 0, 0.15);
    padding: 0.35rem 0;
    width: 100%;
    flex-shrink: 0;
    font-size: 0.8rem;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
} */

.announcement-bar {
    background: rgba(15, 20, 25, 0.95);
    color: rgba(255, 255, 255, 0.85);
    border-bottom: 1px solid rgba(255, 215, 0, 0.15);
    padding: 0.35rem 0;
    width: 100%;
    flex-shrink: 0;
    font-size: 0.8rem;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    height: 40px; /* Fixed height */
    display: flex;
    align-items: center;
}

.announcement-bar marquee {
    padding-top: 1px;
}

.announcement-bar .container {
    max-width: 100%;
    padding: 0 15px;
}

.announcement-bar .row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    margin: 0 -15px;
}

.announcement-bar .col-md-8 {
    flex: 0 0 66.666667%;
    max-width: 66.666667%;
    padding: 0 15px;
}

.announcement-bar .col-md-4 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
    padding: 0 15px;
    text-align: right;
}

.text-gold {
    color: rgba(255, 215, 0, 0.7);
}

.text-md-end {
    text-align: right;
}

.announcement-bar strong {
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
}

.announcement-bar .mx-3 {
    margin: 0 12px;
    color: rgba(255, 255, 255, 0.3);
}

.announcement-bar small {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
}

/* ===== RESPONSIVE STYLES ===== */
@media (max-width: 1200px) {
    .hero-slider {
        min-height: 450px;
    }
}

@media (max-width: 992px) {
    .hero-slider {
        min-height: 400px;
    }
    
    .hero-slider .slide-content h4 {
        font-size: clamp(2rem, 4.2vw, 3.5rem);
        margin-bottom: 1.2rem;
    }
    
    .hero-slider .slide p {
        font-size: clamp(0.95rem, 1.8vw, 1.1rem);
        margin-bottom: 28px;
    }
    
    .hero-slider .slide-content .btn {
        padding: 14px 28px;
        font-size: 1rem;
    }
    
    .announcement-bar {
        padding: 0.3rem 0;
        font-size: 0.75rem;
    }
}

@media (max-width: 768px) {
    .hero-slider {
        min-height: 350px;
    }
    
    .hero-slider .slide {
        padding: 0 5%;
        justify-content: center;
        text-align: center;
    }
    
    .hero-slider .slide-content {
        width: 90%;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.98);
        padding: 0 15px;
        text-align: center;
    }
    
    .hero-slider .slide.active .slide-content {
        transform: translate(-50%, -50%) scale(1);
    }
    
    .hero-slider .slide-content h4 {
        font-size: clamp(1.8rem, 3.8vw, 2.8rem);
        line-height: 1.1;
        margin-bottom: 1rem;
    }
    
    .hero-slider .slide-content h4::after {
        left: 50%;
        transform: translateX(-50%);
        bottom: -6px;
    }
    
    .hero-slider .slide p {
        font-size: clamp(0.9rem, 1.6vw, 1rem);
        margin-bottom: 24px;
        line-height: 1.5;
    }
    
    .hero-slider .slide-content .btn {
        padding: 12px 24px;
        font-size: 0.95rem;
    }
    
    .hero-slider .slider-arrow {
        width: 44px; 
        height: 44px; 
        font-size: 1.3rem;
    }
    
    .hero-slider .slider-arrow.left { left: 15px; }
    .hero-slider .slider-arrow.right { right: 15px; }
    
    .hero-slider .slider-dots {
        bottom: 24px;
        gap: 12px;
        padding: 10px 18px;
    }
    
    .hero-slider .dot {
        width: 12px;
        height: 12px;
    }
    
    /* Announcement bar mobile styles */
    .announcement-bar .col-md-4 {
        text-align: center;
        margin-top: 0.3rem;
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .announcement-bar .col-md-8 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .announcement-bar {
        padding: 0.25rem 0;
        font-size: 0.7rem;
    }
}

@media (max-width: 576px) {
    .hero-slider {
        min-height: 300px;
    }
    
    .hero-slider .slide-content {
        width: 95%;
    }
    
    .hero-slider .slide-content h4 {
        font-size: clamp(1.5rem, 3.2vw, 2.2rem);
        margin-bottom: 0.8rem;
    }
    
    .hero-slider .slide p {
        font-size: clamp(0.85rem, 1.4vw, 0.9rem);
        margin-bottom: 20px;
    }
    
    .hero-slider .slide-content .btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    
    .hero-slider .slider-arrow {
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
    }
    
    .hero-slider .slider-dots {
        bottom: 18px;
        gap: 10px;
        padding: 8px 16px;
    }
    
    .hero-slider .dot {
        width: 10px;
        height: 10px;
    }
    
    .announcement-bar {
        padding: 0.2rem 0;
        font-size: 0.65rem;
    }
    
    .announcement-bar small {
        font-size: 0.7rem;
    }
}

@media (max-width: 400px) {
    .hero-slider {
        min-height: 280px;
    }
    
    .hero-slider .slide-content h4 {
        font-size: clamp(1.3rem, 2.8vw, 1.8rem);
    }
    
    .hero-slider .slide p {
        font-size: 0.8rem;
        margin-bottom: 18px;
    }
    
    .hero-slider .slide-content .btn {
        padding: 8px 18px;
        font-size: 0.85rem;
    }
    
    .hero-slider .slider-arrow {
        width: 34px;
        height: 34px;
        font-size: 1rem;
    }
    
    .hero-slider .slider-dots {
        bottom: 15px;
    }
    
    .announcement-bar {
        padding: 0.18rem 0;
    }
}



    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>

<!-- Hero Slider -->
<div class="hero-slider">
    hiiii
    <button class="slider-arrow left" aria-label="Previous Slide">&#8592;</button>
    <div class="slider-track">
        <!-- Slide 1 -->
        <div class="slide active" style="background-image: url('./assets/images/pic1.jpg')" data-index="0">
            <div class="slide-content">
                <h4>Premium Banking<br> Solutions</h4>
                <p>Experience world-class financial services with our award-winning digital platform</p>
                <a href="#" class="btn">Get Started</a>
            </div>
        </div>
        <!-- Slide 2 -->
        <div class="slide" style="background-image: url('./assets/images/pic2.jpg')" data-index="1">
            <div class="slide-content">
                <h4>Global Investment<br> Opportunities</h4>
                <p>Grow your wealth with our expert portfolio management and investment strategies</p>
                <a href="#" class="btn">Explore Investments</a>
            </div>
        </div>
        <!-- Slide 3 -->
        <div class="slide" style="background-image: url('./assets/images/hm.png')" data-index="2">
            <div class="slide-content">
                <h4>Innovative Digital<br> Banking</h4>
                <p>Bank anytime, anywhere with our secure and intuitive mobile banking app</p>
                <a href="#" class="btn">Download App</a>
            </div>
        </div>
        <!-- Slide 4 -->
        <div class="slide" style="background-image: url('./assets/images/pic4b.jpg')" data-index="3">
            <div class="slide-content">
                <h4>Bank Anywhere,<br> Anytime</h4>
                <p>With our intuitive mobile app, your bank fits right in your pocket — fast, safe, and seamless.</p>
                <a href="#" class="btn">Download App</a>
            </div>
        </div>
        <!-- Slide 5 -->
        <div class="slide" style="background-image: url('./assets/images/pic5.webp')" data-index="4">
            <div class="slide-content">
                <h4>Bulletproof Security</h4>
                <p>We guard your data with top-tier encryption, AI fraud detection, and 24/7 monitoring.</p>
                <a href="#" class="btn">Our Security</a>
            </div>
        </div>
        <!-- Slide 6 -->
        <div class="slide" style="background-image: url('./assets/images/pic0.jpeg')" data-index="5">
            <div class="slide-content">
                <h4>Human-Centered<br> Support</h4>
                <p>Talk to real people who care. No bots, no runaround — just help when you need it.</p>
                <a href="#" class="btn">Talk to Us</a>
            </div>
        </div>
        <!-- Slide 7 -->
        <div class="slide" style="background-image: url('./assets/images/pic7.webp')" data-index="6">
            <div class="slide-content">
                <h4>Future-Ready<br> Technology</h4>
                <p>Our systems evolve with your needs, integrating blockchain, AI, and next-gen APIs.</p>
                <a href="#" class="btn">Tech Overview</a>
            </div>
        </div>
        <!-- Slide 8 -->
        <div class="slide" style="background-image: url('./assets/images/pic8b.jpg')" data-index="7">
            <div class="slide-content">
                <h4>For Entrepreneurs<br>&amp; Innovators</h4>
                <p>Launch, grow, and scale your business with tailored banking built for modern hustlers.</p>
                <a href="#" class="btn">Business Banking</a>
            </div>
        </div>
        <!-- Slide 9 -->
        <div class="slide" style="background-image: url('./assets/images/download.png')" data-index="8">
            <div class="slide-content">
                <h4>Fast International<br> Transfers</h4>
                <p>Send money across borders in seconds with live exchange rates and transparent fees.</p>
                <a href="#" class="btn">Send Money</a>
            </div>
        </div>
        <!-- Slide 10 -->
        <div class="slide" style="background-image: url('./assets/images/pic13.jpg')" data-index="9">
            <div class="slide-content">
                <h4>Smart Credit Solutions</h4>
                <p>Flexible credit lines and intelligent spending tools — built to help you thrive, not just survive.</p>
                <a href="#" class="btn">View Credit Options</a>
            </div>
        </div>
        <!-- Slide 11 -->
        <div class="slide" style="background-image: url('./assets/images/pic12.png')" data-index="10">
            <div class="slide-content">
                <h4>Banking<br> That Matches Your<br> Lifestyle</h4>
                <p>Whether you're a jetsetter or a homebody, our personalized services meet your rhythm.</p>
                <a href="#" class="btn">Discover More</a>
            </div>
        </div>
        <!-- Slide 1 (clone for infinite loop) -->
        <div class="slide" style="background-image: url('./assets/images/pic1.jpg')" data-index="0">
            <div class="slide-content">
                <h4>Premium Banking Solutions</h4>
                <p>Experience world-class financial services with our award-winning digital platform</p>
                <a href="#" class="btn">Get Started</a>
            </div>
        </div>
    </div>
    <button class="slider-arrow right" aria-label="Next Slide">&#8594;</button>
    <div class="slider-dots">
        <div class="dot active" data-index="0"></div>
        <div class="dot" data-index="1"></div>
        <div class="dot" data-index="2"></div>
        <div class="dot" data-index="3"></div>
        <div class="dot" data-index="4"></div>
        <div class="dot" data-index="5"></div>
        <div class="dot" data-index="6"></div>
        <div class="dot" data-index="7"></div>
        <div class="dot" data-index="8"></div>
        <div class="dot" data-index="9"></div>
        <div class="dot" data-index="10"></div>
    </div>
</div>

<!-- Subtle Announcement Bar -->
<div class="announcement-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <marquee behavior="scroll" direction="left" scrollamount="5">
                    <strong>GCC Bank announces new fixed deposit rates at 12.5% p.a. for 12-month term.</strong> 
                    <span class="mx-3">|</span>
                    <strong>Enhanced security measures now active for all online transactions.</strong>
                    <span class="mx-3">|</span>
                    <strong>Voted Best Digital Bank in West Africa 2024 by Global Finance Magazine.</strong>
                </marquee>
            </div>
            <div class="col-md-4 text-md-end">
                <small>24/7 Customer Support: +233 30 911 911</small>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript with Mouse Parallax -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== SLIDER ELEMENTS =====
    const sliderTrack = document.querySelector('.slider-track');
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const leftArrow = document.querySelector('.slider-arrow.left');
    const heroSlider = document.querySelector('.hero-slider');
    const rightArrow = document.querySelector('.slider-arrow.right');
    const buttons = document.querySelectorAll('.btn');
    let currentIndex = 0;
    const slideCount = slides.length - 1; // Exclude clone
    let isTransitioning = false;
    let autoSlideInterval;
    let isTabActive = true;
    const slideDuration = 8000;
    const easeInOut = 'cubic-bezier(0.16, 0.77, 0.22, 0.99)';
    const transitionDuration = 1800;

    // Set all slides as loaded immediately
    slides.forEach(slide => {
        slide.classList.add('loaded');
    });

    // Keyboard navigation
    document.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') prevSlide();
        if (e.key === 'ArrowRight') nextSlide();
    });

    // Touch swipe support
    let startX = 0, endX = 0;
    sliderTrack.addEventListener('touchstart', e => {
        startX = e.touches[0].clientX;
        pauseSlider();
    }, { passive: true });

    sliderTrack.addEventListener('touchmove', e => {
        endX = e.touches[0].clientX;
        e.preventDefault();
    }, { passive: false });

    sliderTrack.addEventListener('touchend', () => {
        if (startX - endX > 60) nextSlide();
        if (endX - startX > 60) prevSlide();
        resumeSlider();
    }, { passive: true });

    function initSlider() {
        startAutoSlide();
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Button hover events
        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', pauseSlider);
            btn.addEventListener('mouseleave', resumeSlider);
        });
        
        dots.forEach(dot => {
            dot.addEventListener('click', function() {
                const targetIndex = parseInt(this.getAttribute('data-index'));
                if (targetIndex !== currentIndex) goToSlide(targetIndex);
            });
        });
        
        leftArrow.addEventListener('click', prevSlide);
        rightArrow.addEventListener('click', nextSlide);

        setTimeout(() => {
            sliderTrack.style.transition = `transform ${transitionDuration}ms ${easeInOut}`;
        }, 50);
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            isTabActive = false;
            pauseSlider();
        } else {
            isTabActive = true;
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }
    }

    function goToSlide(index) {
        if (isTransitioning) return;
        isTransitioning = true;
        currentIndex = index;
        updateDots();
        updateActiveSlide();
        const transformValue = -currentIndex * 100;
        sliderTrack.style.transition = `transform ${transitionDuration}ms ${easeInOut}`;
        sliderTrack.style.transform = `translateX(${transformValue}%)`;
        
        if (currentIndex === slideCount) {
            setTimeout(() => {
                sliderTrack.style.transition = 'none';
                currentIndex = 0;
                sliderTrack.style.transform = 'translateX(0)';
                requestAnimationFrame(() => {
                    updateDots();
                    updateActiveSlide();
                    isTransitioning = false;
                });
            }, transitionDuration);
        } else {
            setTimeout(() => { 
                isTransitioning = false; 
            }, transitionDuration + 100);
        }
    }

    function nextSlide() {
        if (isTransitioning) return;
        goToSlide((currentIndex + 1) % (slideCount + 1));
    }

    function prevSlide() {
        if (isTransitioning) return;
        if (currentIndex === 0) {
            sliderTrack.style.transition = 'none';
            currentIndex = slideCount;
            sliderTrack.style.transform = `translateX(${-currentIndex * 100}%)`;
            requestAnimationFrame(() => {
                goToSlide(slideCount - 1);
            });
        } else {
            goToSlide(currentIndex - 1);
        }
    }

    function updateActiveSlide() {
        slides.forEach((slide, index) => {
            slide.classList.remove('active');
            // Reset transform to prevent peeking
            slide.style.transform = 'translateX(0) scale(1)';
            
            if (index === currentIndex || (currentIndex === slideCount && index === 0)) {
                slide.classList.add('active');
            }
        });
    }

    function updateDots() {
        dots.forEach(dot => {
            dot.classList.remove('active');
            if (parseInt(dot.getAttribute('data-index')) === currentIndex % (slideCount)) {
                dot.classList.add('active');
            }
        });
    }

    // ===== MOUSE PARALLAX EFFECT =====
    let mouseX = 0, mouseY = 0;
    
    heroSlider.addEventListener('mousemove', function(e) {
        const rect = heroSlider.getBoundingClientRect();
        mouseX = (e.clientX - rect.left - rect.width / 2) / rect.width;
        mouseY = (e.clientY - rect.top - rect.height / 2) / rect.height;
        
        const activeSlide = document.querySelector('.slide.active');
        if (activeSlide) {
            const moveX = mouseX * 8; // Subtle 8px max movement
            const moveY = mouseY * 8;
            activeSlide.style.transform = `translate(${moveX}px, ${moveY}px) scale(1.02)`;
        }
    });
    
    heroSlider.addEventListener('mouseleave', function() {
        const activeSlide = document.querySelector('.slide.active');
        if (activeSlide) {
            activeSlide.style.transform = 'translate(0, 0) scale(1)';
        }
    });
    
    // ===== AUTO SLIDE FUNCTIONALITY =====
    function startAutoSlide() {
        clearInterval(autoSlideInterval);
        autoSlideInterval = setInterval(() => {
            if (isTabActive && !isTransitioning) nextSlide();
        }, slideDuration);
    }

    function pauseSlider() { 
        clearInterval(autoSlideInterval); 
    }

    function resumeSlider() { 
        if (isTabActive) startAutoSlide(); 
    }

    initSlider();
});
</script>

</body>
</html>