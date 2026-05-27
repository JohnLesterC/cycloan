<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/landing.css">
    <link rel="stylesheet" href="CSS/steps.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title> CLDD - LOAN PROGRAM</title>
</head>

<body>
    <header class="header">
        <div class="logo-group">
            <img src="IMAGE/logo1.png" alt="Logo 1">
            <img src="IMAGE/logo3.png" alt="Logo 2">
            <img src="IMAGE/logo2.png" alt="Logo 3">
        </div>
    </header>

    <section class="welcome" id="welcome" data-aos="fade-up"></section>

    <!-- Department Section -->
    <section class="department" id="department" data-aos="fade-up">
        <div class="slideshow-container">
            <!-- Full-width images with number and caption text -->
            <div class="mySlides fade">
                <div class="numbertext">1 / 5</div>
                <img src="IMAGE/slider1.png" style="width:100%">
                <div class="steps-btn-container">
                    <button class="steps-btn" onclick="openModal()">STEPS</button>
                </div>
            </div>

            <div class="mySlides fade">
                <div class="numbertext">2 / 5</div>
                <img src="IMAGE/slider2.png" style="width:100%">
            </div>

            <div class="mySlides fade">
                <div class="numbertext">3 / 5</div>
                <img src="IMAGE/slider3.png" style="width:100%">
            </div>

            <div class="mySlides fade">
                <div class="numbertext">4 / 5</div>
                <img src="IMAGE/slider4.png" style="width:100%">
            </div>
            <div class="mySlides fade">
                <div class="numbertext">5 / 5</div>
                <img src="IMAGE/slider5.png" style="width:100%">
            </div>

            <!-- Next and previous buttons -->
            <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
            <a class="next" onclick="plusSlides(1)">&#10095;</a>
        </div>
        <br>

        <!-- The dots/circles -->
        <div style="text-align:center">
            <span class="dot" onclick="currentSlide(1)"></span>
            <span class="dot" onclick="currentSlide(2)"></span>
            <span class="dot" onclick="currentSlide(3)"></span>
            <span class="dot" onclick="currentSlide(4)"></span>
            <span class="dot" onclick="currentSlide(5)"></span>
        </div>

    </section>

    <div id="stepsModal" class="modal">
        <class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="stepIMG">
                <img src="IMAGE/STEPS.jpg" alt="Steps Image">
                </a>
            </div>
    </div>
    </div>

    <section class="map-section" data-aos="fade-up">
        <h2 style="text-align: center;">Our Location</h2>
        <div class="map-container">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.0154030537356!2d121.15736727585563!3d14.193876186245694!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd63dd32315c81%3A0xab6d11d64026d88!2sCalamba%20City%20Hall!5e0!3m2!1sen!2sph!4v1743749918981!5m2!1sen!2sph"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </section>

    <footer>
        <!-- Footer Columns -->
        <div class="footer-col" id="footer" data-aos="fade-up">
            <h4>CLDD CONNECT</h4>
            <ul>
                <li><a href="">Lower Ground Floor(LG)24,New City Hall Complex, Bacnotan Road, Brgy. Real, City of
                        Calamba, Laguna, 4027</a></li>
                <li><a href="">---- --- ----</a></li>
                <li><a href="">---- --- ----</a></li>
            </ul>
        </div>
        <div class="footer-col" data-aos="fade-up">
            <h4>CLDD SERVICES</h4>
            <ul>
                <li><a href="">• Promotion, Education, and Training</a></li>
                <li><a href="">• Livelihood Development and Monitoring </a></li>
                <li><a href="">• Research & Development and Marketing </a></li>
            </ul>
        </div>
        <div class="footer-col" data-aos="fade-up">
            <h4>GET HELP</h4>
            <ul>
                <li><a href="#">• FAQ</a></li>
                <li><a href="#">• Help Center</a></li>
                <li><a href="#">• Contact Us</a></li>
            </ul>
        </div>
        <div class="footer-col" data-aos="fade-up">
            <h4>follow us</h4>
            <div class="links">
                <div class="button">
                    <div class="icon">
                        <a href="https://www.facebook.com/CLDDCalamba"><i class="fab fa-facebook-f"></i></a>
                    </div>
                    <span>CITY GOVERMENT</span>
                </div>
                <div class="button">
                    <div class="icon">
                        <a href=""><i class="fab fa-facebook-f"></i></a>
                    </div>
                    <span>CLDD</span>
                </div>
                <div class="button">
                    <div class="icon">
                        <a href=""><i class="fab fa-facebook-f"></i>
                    </div></a>
                    <span>CLDD</span>
                </div>
                <div class="button">
                    <div class="icon">
                        <a href=""><i class="fab fa-facebook-f"></i></a>
                    </div>
                    <span>CLDD</span>
                </div>
            </div>

    </footer>
    <!-- Bottom Footer Section -->
    <footer-bottom>
        <div class="footer-bottom" data-aos="fade-up">
            <p>©Copyright 2025 City Government of Calamba,
                <span> All Right Reserved </span>
            </p>
        </div>
    </footer-bottom>

    <!-- Inline Script Section -->
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

</body>

<script src="JAVASCRIPT/landing_page.js"></script>

</html>