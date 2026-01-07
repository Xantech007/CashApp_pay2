<?php
session_start();
if (isset($_SESSION['auth'])) {
    header("Location: users/index");
    exit(0);
}
include('includes/header.php');
include('includes/navbar.php');
// Include the countries file
include('users/inc/countries.php');
?>
<!-- Breadcrumb Area Start -->
<section class="breadcrumb-area extra-padding">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <h4 class="title extra-padding">Register</h4>
                <ul class="breadcrumb-list">
                    <li>
                        <a href="index">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li>
                        <span><i class="fas fa-chevron-right"></i></span>
                    </li>
                    <li>
                        <a href="signup">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<!-- Breadcrumb Area End -->

<!-- Signin Area Start -->
<section class="auth">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-10">
                <div class="sign-form">
                    <div class="heading">
                        <h4 class="title">Create account</h4>
                        <p class="subtitle"></p>
                    </div>

                    <style>
                        ::placeholder {
                            color: #ccc !important;
                        }
                        .errors {
                            text-align: center;
                            padding: 7px 0;
                            margin-bottom: 5px;
                            background: linear-gradient(to bottom, #f7941d, #f76b1c);
                            color: white;
                        }
                        .reg-text a {
                            color: #f7951d;
                            font-weight: 600;
                        }
                        .reg-text a:hover,
                        .reg-text a:focus {
                            color: #cc6f0e;
                        }
                        select.form-control {
                            color: black;
                            border: 1px solid #ccc;
                            border-radius: 4px;
                            padding: 10px;
                            width: 100%;
                            background: #fff;
                            -webkit-appearance: none;
                            -moz-appearance: none;
                            appearance: none;
                            background-image: url('data:image/svg+xml;utf8,<svg fill="#000000" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
                            background-repeat: no-repeat;
                            background-position: right 10px center;
                        }
                        select.form-control:focus {
                            border-color: #f7951d;
                            outline: none;
                            box-shadow: 0 0 5px rgba(247, 149, 29, 0.3);
                        }
                        select.form-control option {
                            color: black;
                        }
                        select.form-control option:disabled {
                            color: #ccc;
                        }
                    </style>

                    <form class="form-group mb-0" action="codes/signup" method="POST" enctype="multipart/form-data">
                        <?php
                        if (isset($_SESSION['error'])) { ?>
                            <div class="errors"><?= htmlspecialchars($_SESSION['error']) ?></div>
                        <?php } unset($_SESSION['error']); ?>

                        <input class="form-control" type="text" name="name" placeholder="Enter your Name" style="color:black" required>
                        <input class="form-control" type="email" name="email" placeholder="Email Address" style="color:black" required>
                        <input class="form-control" type="password" name="password" placeholder="Password" style="color:black" required>

                        <select class="form-control" name="country" id="countrySelect" required>
                            <option value="" disabled selected>Select your country</option>
                            <?php
                            foreach ($countries as $country) {
                                echo '<option value="' . htmlspecialchars($country) . '">' . htmlspecialchars($country) . '</option>';
                            }
                            ?>
                        </select>

                        <input class="form-control" type="text" readonly name="ref" placeholder="Referred By" style="color:black" 
                               value="<?php if (isset($_GET['affiliate-link'])) { echo htmlspecialchars($_GET['affiliate-link']); } ?>">

                        <button class="base-btn1" type="submit" name="register">Create Account</button>
                        <p class="reg-text text-center mb-0">Already have an account? <a href="signin">LogIn</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Signin Area End -->

<script>
// Country name normalization map (for fallback/name-based detection)
const countryNameMap = {
    'United States of America': 'United States',
    'United Kingdom of Great Britain and Northern Ireland': 'United Kingdom',
    'Congo, Republic of the': 'Congo (Congo-Brazzaville)',
    'Democratic Republic of Congo': 'Democratic Republic of the Congo',
    'Czech Republic': 'Czechia (Czech Republic)',
    'Swaziland': 'Eswatini',
    'Macedonia': 'North Macedonia',
    'Vatican': 'Vatican City',
    'Timor Leste': 'Timor-Leste',
    'Cape Verde': 'Cabo Verde',
    'Myanmar (Burma)': 'Myanmar',
    'Brunei Darussalam': 'Brunei',
    'Palestinian Territory': 'Palestine'
};

function setCountryInDropdown(country) {
    const countrySelect = document.getElementById('countrySelect');
    const normalizedCountry = countryNameMap[country] || country;

    const options = Array.from(countrySelect.options);
    const matchingOption = options.find(option =>
        option.value.toLowerCase() === normalizedCountry.toLowerCase() ||
        option.text.toLowerCase() === normalizedCountry.toLowerCase()
    );

    if (matchingOption) {
        countrySelect.value = matchingOption.value;
        console.log('Country set to:', matchingOption.value);
    } else {
        console.warn('Country not found in dropdown:', normalizedCountry);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. Try browser geolocation first (less reliable for this use-case)
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const { latitude, longitude } = position.coords;
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=3&addressdetails=1`, {
                    headers: { 'User-Agent': 'RegistrationForm/1.0 (your.email@example.com)' }
                })
                .then(res => res.json())
                .then(data => {
                    const country = data.address?.country;
                    if (country) {
                        setCountryInDropdown(country);
                    } else {
                        fetchIpCountry();
                    }
                })
                .catch(() => fetchIpCountry());
            },
            () => fetchIpCountry()
        );
    } else {
        fetchIpCountry();
    }

    // 2. Most reliable method: IP-based detection with Nigeria → Ghana rule
    function fetchIpCountry() {
        fetch('https://ipapi.co/json/')
            .then(response => response.json())
            .then(data => {
                let country = data.country_name;
                const countryCode = data.country_code; // NG, GH, US, etc.

                if (countryCode === 'NG') {
                    country = 'Ghana';
                    console.log('Nigeria (NG) detected → automatically set to Ghana');
                }

                if (country) {
                    setCountryInDropdown(country);
                }
            })
            .catch(error => {
                console.error('IP detection failed:', error);
            });
    }
});
</script>

<!-- Notification popup (fake withdrawals) -->
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
.mgm a:hover {
    color: #e0c200;
}
</style>

<script type="text/javascript">
var listNames = [
    'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth',
    'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Nancy', 'Thomas', 'Karen', 'Charles', 'Lisa'
];

function getRandomAmount() {
    return Math.floor(Math.random() * (10000 - 500 + 1)) + 500;
}

var interval = Math.floor(Math.random() * (15000 - 5000 + 1)) + 5000;
var run = setInterval(request, interval);

function request() {
    clearInterval(run);
    interval = Math.floor(Math.random() * (15000 - 5000 + 1)) + 5000;

    var name = listNames[Math.floor(Math.random() * listNames.length)];
    var amount = getRandomAmount();
    var msg = '<b>' + name + '</b> just withdrawed <a href="javascript:void(0);">$'+ amount + '</a> from CASHAPP INC. SUPPORT PROGRAM now';

    $(".mgm .txt").html(msg);
    $(".mgm").stop(true).fadeIn(300);

    window.setTimeout(function() {
        $(".mgm").stop(true).fadeOut(300);
    }, 6000);

    run = setInterval(request, interval);
}
</script>

<?php include('includes/footer.php') ?>
