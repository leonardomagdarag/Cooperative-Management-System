<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    @vite('resources/css/public/home.css')
</head>

<body>
    @extends('layouts.app')

    <div class="hero">
        <div class="hero-text">
            <h1>SouthVille 7 Credit Cooperative</h1>
            <p> <b>Strength</b> in members</p>
        </div>
    </div>
    <div class="about">
        <h2>About Us</h2>
        <p>SouthVille 7 Credit Cooperative is dedicated to empowering our members through financial services and
            community support. Our mission is to provide accessible and affordable financial solutions that help our
            members achieve their goals.</p>
        <a href="{{ route('about_page') }}" class="btn-about-us"> About Us</a>
    </div>

    <div class="mission-vision">
        <div class="mission">
            <h3>Our Mission</h3>
            <p>To provide quality financial services that meet the needs of our members while promoting economic growth
                and community development.</p>
        </div>
        <div class="vision">
            <h3>Our Vision</h3>
            <p>To be the leading credit cooperative recognized for excellence in service, innovation, and member
                satisfaction.</p>
        </div>
    </div>
    <div class="requirements">
        <h2>Membership Requirements</h2>
        <ol>
            <li>Must be at least 18 years old.</li>
            <li>Proof of residence in the service area.</li>
            <li>Valid identification (e.g., driver's license, passport).</li>
            <li>Initial deposit to open a savings account.</li>
            <li>Agreement to abide by the cooperative's bylaws and policies.</li>
        </ol>
        <button class="btn-requirements">
            Join Now
        </button>
    </div>
    <div class="stories">
        <h2>Stories</h2>
        <div class="stories-container">
            <button class="btn-stories-card">
                &#8592;
            </button>
            <div class="story-card">
                <img src="https://placehold.co/300x200" alt="Story 1">
                <h4>Story Title 1</h4>
                <p>Brief description of the story.</p>
            </div>
            <div class="story-card">
                <img src="https://placehold.co/300x200" alt="Story 2">
                <h4>Story Title 2</h4>
                <p>Brief description of the story.</p>
            </div>
            <div class="story-card">
                <img src="https://placehold.co/300x200" alt="Story 3">
                <h4>Story Title 3</h4>
                <p>Brief description of the story.</p>
            </div>
            <button class="btn-stories-card">
                &#8594;
            </button>
        </div>

        <a href="{{ route('events_page') }}" class="btn-stories">View All Stories</a>
    </div>
    <div class="contact-location" id="Contact">
        <div class="contact-form">
            <h3>Contact Us</h3>
            <p>Address</p>
            <p>Mobile Number: 09876543210 | 09123456789</p>
            <form action="#" method="post">
                <input type="text" name="name" placeholder="Your Name" required>
                <input type="email" name="email" placeholder="Your Email" required>
                <textarea name="message" rows="4" placeholder="Your Message" required></textarea>
                <button type="submit">Send Message</button>
            </form>
        </div>
        <div class="location-info">
            <h3>Our Location</h3>

        </div>
    </div>
    <footer>
        <p>&copy; 2024 SouthVille 7 Credit Cooperative. All rights reserved.</p>
    </footer>
</body>

</html>
