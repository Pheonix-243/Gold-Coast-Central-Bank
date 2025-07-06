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
      <div class="slide active" style="background-image: url('assests/images/pic3.jpg')">
        <div class="slide-content">
          <h1>Slide One Heading</h1>
          <p>Subheading text goes here.</p>
          <a href="#services" class="btn">Get Started</a>
        </div>
      </div>
      <div class="slide" style="background-image: url('assests/images/pic2.jpg')">
        <div class="slide-content">
          <h1>Slide Two Heading</h1>
          <p>Another subtitle for the second slide.</p>
          <a href="#contact" class="btn">Contact Us</a>
        </div>
      </div>
      <div class="slide" style="background-image: url('assests/images/pic1.jpg')">
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
let slideInterval = setInterval(nextSlide, 5000);

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
  slideInterval = setInterval(nextSlide, 5000);
}

  </script>
</body>
</html>
