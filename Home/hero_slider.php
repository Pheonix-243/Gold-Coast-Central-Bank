<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Banking Solutions</title>
    <!-- Preload first image for better performance -->
    <link rel="preload" href="./assets/images/pic1.jpg" as="image" fetchpriority="high">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            overflow-x: hidden;
        }
        .hero-slider {
            background: linear-gradient(120deg, #232526 0%, #414345 100%);
            position: relative;
            width: 100%;
            height: 95vh;
            min-height: 600px;
            max-height: 1200px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            margin: 0;
            margin-top: -8px;
        }
        .hero-slider .slider-track {
            display: flex;
            height: 100%;
            width: 100%;
            transition: transform 1800ms cubic-bezier(0.16, 0.77, 0.22, 0.99);
            will-change: transform;
        }
        .hero-slider .slide {
            min-width: 100%;
            height: 100%;
            position: relative;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            flex-shrink: 0;
            overflow: hidden;
            filter: brightness(0.7);
            transition: filter 1600ms cubic-bezier(0.16, 0.77, 0.22, 0.99);
            will-change: transform, filter;
        }
        .hero-slider .slide.loaded {
            filter: brightness(0.92) saturate(1.1);
        }
        .hero-slider .slide.active {
            filter: brightness(1) saturate(1.2) drop-shadow(0 0 40px #fff2);
        }
        .hero-slider .slide-content {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(0.98);
            width: 90%; max-width: 1200px;
            text-align: center;
            color: #fff;
            z-index: 2;
            opacity: 0;
            filter: blur(8px);
            transition: 
                opacity 1400ms cubic-bezier(0.16, 0.77, 0.22, 0.99) 400ms,
                filter 1400ms cubic-bezier(0.16, 0.77, 0.22, 0.99) 400ms,
                transform 1400ms cubic-bezier(0.16, 0.77, 0.22, 0.99) 400ms;
            will-change: opacity, filter, transform;
        }
        .hero-slider .slide.active .slide-content {
            opacity: 1;
            filter: blur(0);
            transform: translate(-50%, -50%) scale(1.04);
        }
        .hero-slider .slide h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            margin-bottom: 1.2rem;
            font-weight: 900;
            letter-spacing: 0.03em;
            text-shadow: 0 4px 24px rgba(0,0,0,0.4), 0 1px 0 #fff3;
            background: linear-gradient(90deg, #4a6ee0 30%, #00e0ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-slider .slide p {
            font-size: clamp(1.1rem, 2.2vw, 1.6rem);
            margin-bottom: 2.2rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 2px 8px rgba(0,0,0,0.25);
            color: #eaf6ff;
        }
        .hero-slider .slide-content .btn {
            display: inline-block;
            padding: 14px 38px;
            background: linear-gradient(90deg, #4a6ee0 0%, #00e0ff 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 32px;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.04em;
            box-shadow: 0 4px 24px #00e0ff33;
            transition: all 0.35s cubic-bezier(0.16, 0.77, 0.22, 0.99), box-shadow 0.5s cubic-bezier(0.16, 0.77, 0.22, 0.99);
            position: relative;
            overflow: hidden;
        }
        .hero-slider .slide-content .btn::before {
            content: '';
            position: absolute;
            left: -75%; top: 0; width: 50%; height: 100%;
            background: linear-gradient(120deg, #fff8 0%, #fff0 100%);
            transform: skewX(-20deg);
            transition: left 0.7s cubic-bezier(0.16, 0.77, 0.22, 0.99);
            z-index: 1;
        }
        .hero-slider .slide-content .btn:hover {
            background: linear-gradient(90deg, #00e0ff 0%, #4a6ee0 100%);
            box-shadow: 0 8px 32px #00e0ff66;
            transform: translateY(-2px) scale(1.04);
        }
        .hero-slider .slide-content .btn:hover::before {
            left: 120%;
        }
        /* Navigation Dots */
        .hero-slider .slider-dots {
            position: absolute;
            bottom: 38px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 18px;
            z-index: 10;
        }
       .hero-slider .dot {
            width: 16px; height: 16px;
            border-radius: 50%;
            background: rgba(255,255,255,0.45);
            cursor: pointer;
            box-shadow: 0 2px 8px #0002;
            transition: all 0.5s cubic-bezier(0.16, 0.77, 0.22, 0.99);
            position: relative;
            will-change: transform, background;
        }
        .hero-slider .dot.active {
            background: linear-gradient(90deg, #4a6ee0 0%, #00e0ff 100%);
            transform: scale(1.25);
            box-shadow: 0 4px 16px #00e0ff55;
        }
        .hero-slider .dot.active::after {
            content: '';
            position: absolute;
            left: 50%; top: 50%;
            transform: translate(-50%, -50%);
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 2px solid #00e0ff55;
            opacity: 0.5;
            animation: pulseDot 1.8s infinite cubic-bezier(0.16, 0.77, 0.22, 0.99);
        }
        @keyframes pulseDot {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            60% { transform: translate(-50%, -50%) scale(1.3); opacity: 0.15; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
        }
        /* Arrows */
        .hero-slider .slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 54px; height: 54px;
            background: rgba(0,0,0,0.18);
            border: none;
            border-radius: 50%;
            color: #fff;
            font-size: 2.2rem;
            cursor: pointer;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.16, 0.77, 0.22, 0.99);
            box-shadow: 0 2px 12px #0002;
            will-change: transform, background;
        }
        .hero-slider .slider-arrow:hover {
            background: linear-gradient(120deg, #4a6ee0 0%, #00e0ff 100%);
            color: #fff;
            box-shadow: 0 4px 24px #00e0ff44;
            transform: translateY(-50%) scale(1.08);
        }
        .hero-slider .slider-arrow.left { left: 32px; }
        .hero-slider .slider-arrow.right { right: 32px; }
        
        /* Responsive adjustments - UPDATED FOR BETTER MOBILE DISPLAY */
        @media (max-width: 1200px) {
            .hero-slider {
                height: 85vh;
                min-height: 500px;
            }
        }
        
        @media (max-width: 992px) {
            .hero-slider {
                height: 80vh;
                min-height: 450px;
            }
        }
        
        @media (max-width: 768px) {
            .hero-slider {
                height: 75vh;
                min-height: 400px;
            }
            .hero-slider .slide-content {
                width: 95%;
                top: 45%;
                left: 50%;
                right: auto;
                transform: translate(-50%, -50%) scale(0.98);
                padding: 0 15px;
            }
            .hero-slider .slide.active .slide-content {
                transform: translate(-50%, -50%) scale(1);
            }
            .hero-slider .slider-arrow {
                width: 38px; 
                height: 38px; 
                font-size: 1.3rem;
            }
            .hero-slider .slider-arrow.left { left: 12px; }
            .hero-slider .slider-arrow.right { right: 12px; }
            .hero-slider .slider-dots {
                bottom: 24px;
                gap: 12px;
            }
            .hero-slider .dot {
                width: 12px;
                height: 12px;
            }
            .hero-slider .dot.active::after {
                width: 22px;
                height: 22px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-slider {
                height: 70vh;
                min-height: 350px;
            }
            .hero-slider .slide-content {
                top: 45%;
                width: 90%;
            }
            .hero-slider .slide h1 {
                font-size: 1.8rem;
                margin-bottom: 0.8rem;
            }
            .hero-slider .slide p {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }
            .hero-slider .slide-content .btn {
                padding: 10px 28px;
                font-size: 0.9rem;
            }
            .hero-slider .slider-arrow {
                width: 32px;
                height: 32px;
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 400px) {
            .hero-slider {
                height: 65vh;
                min-height: 300px;
            }
            .hero-slider .slide-content {
                top: 45%;
                width: 90%;
            }
            .hero-slider .slide h1 {
                font-size: 1.6rem;
            }
            .hero-slider .slide p {
                font-size: 0.9rem;
            }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>

<!-- Rest of your HTML content remains exactly the same -->
<div class="hero-slider">
    <button class="slider-arrow left" aria-label="Previous Slide">&#8592;</button>
    <div class="slider-track">
        <!-- Slide 1 -->
        <div class="slide active" style="background-image: url('./assets/images/pic1.jpg')" data-index="0">
            <div class="slide-content">
                <h1>Premium Banking Solutions</h1>
                <p>Experience world-class financial services with our award-winning digital platform</p>
                <a href="#" class="btn">Get Started</a>
            </div>
        </div>
        <!-- Slide 2 -->
        <div class="slide" style="background-image: url('./assets/images/pic2.jpg')" data-index="1">
            <div class="slide-content">
                <h1>Global Investment Opportunities</h1>
                <p>Grow your wealth with our expert portfolio management and investment strategies</p>
                <a href="#" class="btn">Explore Investments</a>
            </div>
        </div>
        <!-- Slide 3 -->
        <div class="slide" style="background-image: url('./assets/images/pic3.jpg')" data-index="2">
            <div class="slide-content">
                <h1>Innovative Digital Banking</h1>
                <p>Bank anytime, anywhere with our secure and intuitive mobile banking app</p>
                <a href="#" class="btn">Download App</a>
            </div>
        </div>
        <!-- Slide 4 -->
        <div class="slide" style="background-image: url('./assets/images/pic4b.jpg')" data-index="3">
            <div class="slide-content">
                <h1>Bank Anywhere, Anytime</h1>
                <p>With our intuitive mobile app, your bank fits right in your pocket — fast, safe, and seamless.</p>
                <a href="#" class="btn">Download App</a>
            </div>
        </div>
        <!-- Slide 5 -->
        <div class="slide" style="background-image: url('./assets/images/pic5.webp')" data-index="4">
            <div class="slide-content">
                <h1>Bulletproof Security</h1>
                <p>We guard your data with top-tier encryption, AI fraud detection, and 24/7 monitoring.</p>
                <a href="#" class="btn">Our Security</a>
            </div>
        </div>
        <!-- Slide 6 -->
        <div class="slide" style="background-image: url('./assets/images/pic0.jpeg')" data-index="5">
            <div class="slide-content">
                <h1>Human-Centered Support</h1>
                <p>Talk to real people who care. No bots, no runaround — just help when you need it.</p>
                <a href="#" class="btn">Talk to Us</a>
            </div>
        </div>
        <!-- Slide 7 -->
        <div class="slide" style="background-image: url('./assets/images/pic7.webp')" data-index="6">
            <div class="slide-content">
                <h1>Future-Ready Technology</h1>
                <p>Our systems evolve with your needs, integrating blockchain, AI, and next-gen APIs.</p>
                <a href="#" class="btn">Tech Overview</a>
            </div>
        </div>
        <!-- Slide 8 -->
        <div class="slide" style="background-image: url('./assets/images/pic8b.jpg')" data-index="7">
            <div class="slide-content">
                <h1>For Entrepreneurs & Innovators</h1>
                <p>Launch, grow, and scale your business with tailored banking built for modern hustlers.</p>
                <a href="#" class="btn">Business Banking</a>
            </div>
        </div>
        <!-- Slide 9 -->
        <div class="slide" style="background-image: url('./assets/images/pic9.jpg')" data-index="8">
            <div class="slide-content">
                <h1>Fast International Transfers</h1>
                <p>Send money across borders in seconds with live exchange rates and transparent fees.</p>
                <a href="#" class="btn">Send Money</a>
            </div>
        </div>
        <!-- Slide 10 -->
        <div class="slide" style="background-image: url('./assets/images/pic13.jpg')" data-index="9">
            <div class="slide-content">
                <h1>Smart Credit Solutions</h1>
                <p>Flexible credit lines and intelligent spending tools — built to help you thrive, not just survive.</p>
                <a href="#" class="btn">View Credit Options</a>
            </div>
        </div>
        <!-- Slide 11 -->
        <div class="slide" style="background-image: url('./assets/images/pic12.png')" data-index="10">
            <div class="slide-content">
                <h1>Banking That Matches Your Lifestyle</h1>
                <p>Whether you're a jetsetter or a homebody, our personalized services meet your rhythm.</p>
                <a href="#" class="btn">Discover More</a>
            </div>
        </div>
        <!-- Slide 1 (clone for infinite loop) -->
        <div class="slide" style="background-image: url('./assets/images/pic1.jpg')" data-index="0">
            <div class="slide-content">
                <h1>Premium Banking Solutions</h1>
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

<!-- Your JavaScript remains exactly the same -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sliderTrack = document.querySelector('.slider-track');
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const leftArrow = document.querySelector('.slider-arrow.left');
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