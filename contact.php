<?php
session_start(); 
include('includes/header.php');
include('includes/navbar.php'); 
?>

<!-- Breadcrumb Area Start -->
<section class="breadcrumb-area">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <h4 class="title">
                    Contact
                </h4>
                <ul class="breadcrumb-list">
                    <li>
                        <a href="index">
                            <i class="fas fa-home"></i>
                            Home
                        </a>
                    </li>
                    <li>
                        <span><i class="fas fa-chevron-right"></i> </span>
                    </li>
                    <li>
                        <a href="contact">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<!-- Breadcrumb Area End -->

<!-- Contact Area Start -->
<section class="contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-heading">
                    <h2 class="title">
                        Get in Touch
                    </h2>
                    <p class="text">
                        Have questions about scanning CashTags or withdrawing your rewards? Contact us, and our team will assist you promptly.
                    </p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8">
                <div class="contact-form-wrapper">
                    <form method="post" action="">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="name">Name :</label>
                                    <input type="text" class="input-field" id="name" placeholder="Enter Your Name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="email">Email :</label>
                                    <input type="text" class="input-field" id="email" placeholder="Enter Your Email">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="form-group button-area">
                                    <label for="message">Message :</label>
                                    <textarea id="message" class="input-field textarea" placeholder="Write Your Message"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="form-group button-area">
                                    <button type="submit" class="base-btn1">Send <i class="fas fa-paper-plane"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4 d-flex">
                <div class="address-area">
                    <h4 class="title">
                        Contact Information
                    </h4>
                    <ul class="address-list">
                        <li>
                            <p>
                                <i class="fas fa-map-marker-alt"></i> 456 Lexington Building, 23rd Street, Brooklyn Heights, Brooklyn, NYC, USA
                            </p>
                        </li>
                        <li>
                            <p>
                                <i class="fab fa-whatsapp"></i>
                                <a href="https://wa.me/13072924199" target="_blank" rel="noopener noreferrer">
                                    wa.me/13072924199
                                </a>
                            </p>
                        </li>
                        <li>
                            <p>
                                <i class="far fa-envelope"></i>
                                <a href="mailto:pay1.cashapp@gmail.com">
                                    pay1.cashapp@gmail.com
                                </a>
                            </p>
                        </li>
                        <li>
                            <p>
                                <i class="fas fa-globe-americas"></i>
                                https://pay1.cashapp.ct.ws
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Contact Area End -->

<?php include('includes/footer.php'); ?>
