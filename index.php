<?php
session_start(); 
include('includes/header.php');
include('includes/navbar.php'); 
?>

<!-- Hero Area Start -->
<div class="hero-area" id="hero-area" style="margin-top:20px">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 align-self-center">
                <div class="left-content" style="width:100%">
                    <div class="content">
                        <h1 class="title">
                            Scan CashTags to Claim and Withdraw Rewards
                        </h1>
                        <p class="text">
                            Unlock exciting rewards and withdraw cash instantly by scanning CashTags with our secure platform.
                        </p>
                        <?php
                        if(isset($_SESSION['admin']))
                        { ?>
                            <a href="admin/signin" class="base-btn2">Admin</a>
                        <?php }
                        else if(isset($_SESSION['auth']))
                        { ?>
                            <a href="signup" class="base-btn2">Dashboard</a>
                        <?php }
                        else
                        { ?>
                            <a href="signup" class="base-btn2">Get Started</a>                            
                        <?php }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Hero Area End -->

<!-- Top Counter Start -->
<section class="top-counter">
    <div class="container">
        <div class="col-lg-12">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="single-counter">
                        <div class="icon">
                            <i class="flaticon-community"></i>
                        </div>
                        <div class="content">
                            <h4 class="title">
                                73,234
                            </h4>
                            <h6 class="sub-title">
                                Total Users
                            </h6>
                            <p>
                                Join our growing community of users scanning CashTags to claim rewards.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="single-counter">
                        <div class="icon">
                            <i class="flaticon-purse"></i>
                        </div>
                        <div class="content">
                            <h4 class="title">
                                60,548.34
                            </h4>
                            <h6 class="sub-title">
                                Total Rewards Claimed
                            </h6>
                            <p>
                                Users have claimed thousands in rewards by scanning CashTags.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="single-counter">
                        <div class="icon">
                            <i class="flaticon-money"></i>
                        </div>
                        <div class="content">
                            <h4 class="title">
                                90,548.37
                            </h4>
                            <h6 class="sub-title">
                                Total Withdrawn
                            </h6>
                            <p>
                                Cash out your rewards quickly and securely with our platform.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Top Counter End -->

<!-- About-area Start -->
<section class="about-area">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-heading">
                    <h2 class="title">
                        About Us
                    </h2>
                    <p class="text">
                        We provide a seamless platform to scan CashTags, claim rewards, and withdraw funds effortlessly using cutting-edge technology.
                    </p>
                </div>
            </div>
        </div>
        <div class="row mb-100">
            <div class="col-lg-6 d-flex align-self-center">
                <div class="image">
                    <img src="assets/images/about.png" alt="">
                </div>
            </div>
            <div class="col-lg-6 align-self-center">
                <div class="content">
                    <div class="top-heading">
                        <h4 class="title">
                            The Easiest Way to Claim Rewards
                        </h4>
                        <p class="text">
                            Scan CashTags to unlock rewards and withdraw funds instantly with our user-friendly platform.
                        </p>
                    </div>
                    <ul class="about-list with-icon">
                        <li>
                            <div class="feature-info">
                                <div class="icon">
                                    <i class="flaticon-sticker"></i>
                                </div>
                                <div class="inner-content">
                                    <h4 class="title">
                                        Licensed & Secure
                                    </h4>
                                    <p>
                                        Our platform is fully licensed and ensures secure transactions for all users.
                                    </p>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="feature-info">
                                <div class="icon">
                                    <i class="flaticon-save-money"></i>
                                </div>
                                <div class="inner-content">
                                    <h4 class="title">
                                        Easy CashTag Scanning
                                    </h4>
                                    <p>
                                        Scan CashTags to claim rewards in just a few clicks.
                                    </p>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="feature-info">
                                <div class="icon">
                                    <i class="flaticon-user"></i>
                                </div>
                                <div class="inner-content">
                                    <h4 class="title">
                                        Instant Withdrawals
                                    </h4>
                                    <p>
                                        Withdraw your rewards quickly and securely to your preferred account.
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- About-area End -->

<!-- How It Work Start -->
<section class="how-it-work">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-heading text-white">
                    <h2 class="title">
                        How It Works
                    </h2>
                    <p class="text">
                        Our platform makes it simple to scan CashTags, claim rewards, and withdraw funds in just a few steps.
                    </p>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6">
                <div class="single-how-it-work">
                    <div class="icon">
                        <i class="flaticon-user-2"></i>
                    </div>
                    <div class="content">
                        <h4 class="title">
                            Create Account
                        </h4>
                        <p>
                            Sign up to start scanning CashTags and claiming rewards.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="single-how-it-work">
                    <div class="icon">
                        <i class="flaticon-calendar"></i>
                    </div>
                    <div class="content">
                        <h4 class="title">
                            Scan CashTags
                        </h4>
                        <p>
                            Use our platform to scan CashTags and unlock exciting rewards.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="single-how-it-work">
                    <div class="icon">
                        <i class="flaticon-megaphone"></i>
                    </div>
                    <div class="content">
                        <h4 class="title">
                            Withdraw Funds
                        </h4>
                        <p>
                            Cash out your rewards instantly to your preferred account.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- How It Work End -->

<!-- Why Choose Us Start -->
<section class="choose_us">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-heading">
                    <h2 class="title extra-padding">
                        Why Choose Us
                    </h2>
                    <p class="text">
                        Our platform offers a fast, secure, and easy way to claim and withdraw rewards using CashTags.
                    </p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <ul class="nav cu-menu" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pills-c1-tab" data-toggle="pill" href="#pills-c1" role="tab" aria-controls="pills-c1" aria-selected="true">
                            <div class="icon">
                                <i class="flaticon-money"></i>
                            </div>
                            <h4 class="title">
                                Rewards
                            </h4>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="pills-c2-tab" data-toggle="pill" href="#pills-c2" role="tab" aria-controls="pills-c2" aria-selected="false">
                            <div class="icon">
                                <i class="flaticon-withdraw"></i>
                            </div>
                            <h4 class="title">
                                Withdraw
                            </h4>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="pills-c3-tab" data-toggle="pill" href="#pills-c3" role="tab" aria-controls="pills-c3" aria-selected="false">
                            <div class="icon">
                                <i class="flaticon-money-1"></i>
                            </div>
                            <h4 class="title">
                                Multi-Currency
                            </h4>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="pills-c4-tab" data-toggle="pill" href="#pills-c4" role="tab" aria-controls="pills-c4" aria-selected="false">
                            <div class="icon">
                                <i class="flaticon-support"></i>
                            </div>
                            <h4 class="title">
                                Support
                            </h4>
                        </a>
                    </li>
                </ul>
                <div class="tab-content cu-content">
                    <div class="tab-pane fade show active" id="pills-c1" role="tabpanel" aria-labelledby="pills-c1-tab">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="image">
                                    <img src="assets/images/rewards.png" alt="">
                                </div>
                            </div>
                            <div class="col-md-7 d-flex align-self-center">
                                <div class="content">
                                    <div class="heading">
                                        <h4 class="title">
                                            Instant Rewards
                                        </h4>
                                        <p class="text">
                                            Scan CashTags to claim rewards quickly and easily. Our platform ensures you get your rewards without delay.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pills-c2" role="tabpanel" aria-labelledby="pills-c2-tab">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="image">
                                    <img src="assets/images/withdraw.png" alt="">
                                </div>
                            </div>
                            <div class="col-md-7 d-flex align-self-center">
                                <div class="content">
                                    <div class="heading">
                                        <h4 class="title">
                                            Instant Withdraw
                                        </h4>
                                        <p class="text">
                                            Withdraw your rewards instantly to your preferred account with no hassle.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pills-c3" role="tabpanel" aria-labelledby="pills-c3-tab">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="image">
                                    <img src="assets/images/currency.png" alt="">
                                </div>
                            </div>
                            <div class="col-md-7 d-flex align-self-center">
                                <div class="content">
                                    <div class="heading">
                                        <h4 class="title">
                                            Multi-Currency Support
                                        </h4>
                                        <p class="text">
                                            Withdraw rewards in multiple currencies, making it convenient for users worldwide.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pills-c4" role="tabpanel" aria-labelledby="pills-c4-tab">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="image">
                                    <img src="assets/images/support.png" alt="">
                                </div>
                            </div>
                            <div class="col-md-7 d-flex align-self-center">
                                <div class="content">
                                    <div class="heading">
                                        <h4 class="title">
                                            24/7 Customer Support
                                        </h4>
                                        <p class="text">
                                            Our support team is available around the clock to assist with any questions or issues.
                                        </p>
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
<!-- Why Choose Us End -->

<!-- Get Start Area Start -->
<section class="ger-start-secrion" style="background:#fff9ed">
    <div class="container">
        <div class="row">
            <div class="col-lg-5">
                <h2 class="title">
                    <?php
                    $name = "SELECT name FROM settings";
                    $name_query = mysqli_query($con, $name);

                    if($name_query)
                    {
                        $row = mysqli_fetch_array($name_query);
                        $name = $row['name'];
                    }
                    ?>
                    Start Claiming Rewards with <?= $name ?>
                </h2>
            </div>
            <div class="col-lg-7 d-flex align-self-center">
                <div class="right-links">
                    <a href="signup" class="base-btn1">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Get Start Area End -->

<!-- Start invest Area Start -->
<section class="start-invest">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-heading">
                    <h2 class="title">
                        Supporting Our Elderly & Handicapped Community
                    </h2>
                    <p class="text">
                        We are proud to support elderly and handicapped individuals across the USA by providing accessible financial services through our CashTag platform.
                    </p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-all-tabthree" role="tabpanel" aria-labelledby="pills-all-tabthree-tab">
                        <div class="responsive-table">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">USER</th>
                                        <th scope="col">NAME</th>
                                        <th scope="col">RECEIVED AMOUNT</th>
                                        <th scope="col">COUNTRY</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p1.png" alt="Margaret Johnson" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Margaret Johnson
                                        </td>
                                        <td>
                                            $14,750
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p2.png" alt="James Carter" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            James Carter
                                        </td>
                                        <td>
                                            $22,300
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p3.png" alt="Patricia Davis" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Patricia Davis
                                        </td>
                                        <td>
                                            $18,900
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p4.png" alt="Robert Wilson" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Robert Wilson
                                        </td>
                                        <td>
                                            $29,100
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p5.png" alt="Susan Thompson" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Susan Thompson
                                        </td>
                                        <td>
                                            $11,600
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p6.png" alt="Thomas Anderson" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Thomas Anderson
                                        </td>
                                        <td>
                                            $26,400
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p7.png" alt="Linda Brown" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Linda Brown
                                        </td>
                                        <td>
                                            $17,800
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p8.png" alt="Michael Harris" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Michael Harris
                                        </td>
                                        <td>
                                            $24,500
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p9.png" alt="Barbara Clark" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            Barbara Clark
                                        </td>
                                        <td>
                                            $13,200
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="assets/images/people/p10.png" alt="William Lewis" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                        </td>
                                        <td>
                                            William Lewis
                                        </td>
                                        <td>
                                            $27,900
                                        </td>
                                        <td>
                                            USA <img src="assets/images/flags/usa.png" alt="USA" style="width:24px; height:16px;">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Start invest Area End -->

<!-- Testimonial Start -->
<section class="testimonial" style="background:#ffffff">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-heading text-white">
                    <h2 class="title extra-padding">
                        What Our Users Are Saying
                    </h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="testimonial-slider">
                    <div class="slider-item">
                        <div class="single-review">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="content">
                                <p>
                                    Scanning CashTags to claim rewards was so easy, and I withdrew my funds instantly!
                                </p>
                            </div>
                            <div class="reviewr">
                                <div class="img">
                                    <img src="assets/images/reviewr/p1.png" alt="">
                                </div>
                                <div class="content">
                                    <h4 class="name">
                                        Austin Bishop
                                    </h4>
                                    <p>
                                        CEO at AB Oil & Gas
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="slider-item">
                        <div class="single-review">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="content">
                                <p>
                                    The platform made claiming rewards a breeze, and withdrawals were super fast!
                                </p>
                            </div>
                            <div class="reviewr">
                                <div class="img">
                                    <img src="assets/images/reviewr/p2.png" alt="">
                                </div>
                                <div class="content">
                                    <h4 class="name">
                                        Helena
                                    </h4>
                                    <p>
                                        Works at Dennie's Super Market
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="slider-item">
                        <div class="single-review">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="content">
                                <p>
                                    I've claimed multiple rewards using CashTags, and every withdrawal was smooth and quick.
                                </p>
                            </div>
                            <div class="reviewr">
                                <div class="img">
                                    <img src="assets/images/reviewr/p3.png" alt="">
                                </div>
                                <div class="content">
                                    <h4 class="name">
                                        Sebastian
                                    </h4>
                                    <p>
                                        Founder of DC Electronics
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="slider-item">
                        <div class="single-review">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="content">
                                <p>
                                    The platform is reliable, and withdrawing my rewards was effortless.
                                </p>
                            </div>
                            <div class="reviewr">
                                <div class="img">
                                    <img src="assets/images/reviewr/p4.png" alt="">
                                </div>
                                <div class="content">
                                    <h4 class="name">
                                        Christopher
                                    </h4>
                                    <p>
                                        CEO at XYZ Automobiles
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Testimonial End -->

<!-- Faq Area Start -->
<section class="faq-area" style="background:#fff9ed">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 d-flex align-self-center">
                <div class="section-heading">
                    <h2 class="title extra-paddin">
                        Frequently Asked <br>
                        Questions
                    </h2>
                    <p class="text">
                        Common questions about scanning CashTags and withdrawing rewards.
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="content">
                    <div class="accordion" id="tour-faq">
                        <div class="single-accordion">
                            <div class="accordion-header">
                                <h4 class="title" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How do I start?
                                </h4>
                            </div>
                            <div id="collapseOne" class="collapse show" data-parent="#tour-faq">
                                <div class="accordion-body">
                                    To register a new account, simply click the "Register New Account" button or "Sign Up" link and fill out the required information.
                                </div>
                            </div>
                        </div>
                        <div class="single-accordion">
                            <div class="accordion-header">
                                <h4 class="title collapsed" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    What is the minimum and maximum withdrawal?
                                </h4>
                            </div>
                            <div id="collapseTwo" class="collapse" data-parent="#tour-faq">
                                <div class="accordion-body">
                                    The minimum withdrawal amount is $50, and the maximum you can withdraw at one time is $10,000 per transaction. You can make multiple withdrawals if needed.
                                </div>
                            </div>
                        </div>
                        <div class="single-accordion">
                            <div class="accordion-header">
                                <h4 class="title collapsed" data-toggle="collapse" data-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    How do I request a withdrawal?
                                </h4>
                            </div>
                            <div id="collapseThree" class="collapse" data-parent="#tour-faq">
                                <div class="accordion-body">
                                    You can request a withdrawal by clicking the “Withdraw” button in the member's area and entering the amount you want to withdraw.
                                </div>
                            </div>
                        </div>
                        <div class="single-accordion">
                            <div class="accordion-header">
                                <h4 class="title collapsed" data-toggle="collapse" data-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    How do I change my withdrawal account?
                                </h4>
                            </div>
                            <div id="collapseFour" class="collapse" data-parent="#tour-faq">
                                <div class="accordion-body">
                                    You can change your withdrawal account by updating your profile details “on your dashboard” after logging in to your account. Enter your new account details and save the changes.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Faq Area End -->

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
                        Contact us for any questions about scanning CashTags or withdrawing rewards.
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
                                <i class="fas fa-phone"></i> +44 7438 783028
                            </p>
                        </li>
                        <li>
                            <p>
                                <i class="far fa-envelope"></i>
                                pay1.cashapp@gmail.com
                            </p>
                        </li>
                        <li>
                            <p>
                                <i class="fas fa-globe-americas"></i>
                                www.pay1.cashapp.gt.tc
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Contact Area End -->

<div class="mgm" style="display: none;">
    <div class="txt" style="color:black;"></div>
</div>

<style>
.mgm {
    border-radius: 7px;
    position: fixed;
    z-index: 90;
    bottom: 80px;
    right: 50px;
    background: #fff;
    padding: 10px 27px;
    box-shadow: 0px 5px 13px 0px rgba(0,0,0,.3);
}
.mgm a {
    font-weight: 700;
    display: block;
    color: #f2d516;
}
.mgm a, .mgm a:active {
    transition: all .2s ease;
    color: #f2d516;
}
</style>

<script type="text/javascript">
var listNames = [
    'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth',
    'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Nancy', 'Thomas', 'Karen', 'Charles', 'Lisa',
    'Christopher', 'Sarah', 'Daniel', 'Betty', 'Matthew', 'Margaret', 'Mark', 'Dorothy', 'Steven', 'Helen',
    'Paul', 'Sandra', 'George', 'Ashley', 'Kenneth', 'Donna', 'Andrew', 'Carol', 'Edward', 'Michelle',
    'Joshua', 'Emily', 'Donald', 'Amanda', 'Ronald', 'Melissa', 'Timothy', 'Deborah', 'Jason', 'Laura',
    'Jeffrey', 'Rebecca', 'Ryan', 'Sharon', 'Jacob', 'Cynthia', 'Gary', 'Kathleen', 'Nicholas', 'Amy',
    'Eric', 'Shirley', 'Jonathan', 'Angela', 'Stephen', 'Ruth', 'Larry', 'Brenda', 'Justin', 'Pamela',
    'Scott', 'Nicole', 'Brandon', 'Samantha', 'Benjamin', 'Katherine', 'Samuel', 'Christine', 'Gregory', 'Debra',
    'Brian', 'Rachel', 'Patrick', 'Carolyn', 'Frank', 'Janet', 'Raymond', 'Catherine', 'Dennis', 'Virginia',
    'Jerry', 'Maria', 'Tyler', 'Heather', 'Aaron', 'Diane', 'Jose', 'Julie', 'Adam', 'Joyce'
];

function getRandomAmount() {
    return Math.floor(Math.random() * (10000 - 500 + 1)) + 500;
}

var interval = Math.floor(Math.random() * (15000 - 5000 + 1) + 5000);
var run = setInterval(request, interval);

function request() {
    clearInterval(run);
    interval = Math.floor(Math.random() * (15000 - 5000 + 1) + 5000);
    var name = listNames[Math.floor(Math.random() * listNames.length)];
    var amount = getRandomAmount();
    var msg = '<b>' + name + '</b> just withdrawed <a href="javascript:void(0);" onclick="javascript:void(0);">$'+ amount + '</a> from CASHAPP INC. SUPPORT PROGRAM now';
    $(".mgm .txt").html(msg);
    $(".mgm").stop(true).fadeIn(300);
    window.setTimeout(function() {
        $(".mgm").stop(true).fadeOut(300);
    }, 6000);
    run = setInterval(request, interval);
}
</script>

<?php include('includes/footer.php') ?>
