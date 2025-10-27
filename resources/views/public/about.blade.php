<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite('resources/css/public/about.css')
    <title>About</title>
</head>

<body>
    @extends('layouts.app')

    <header>
        <h1>Overview</h1>
        <p>SouthVille 7 Credit Cooperative is dedicated to empowering our members through financial services and
            community support. Our mission is to provide accessible and affordable financial solutions that help our
            members achieve their goals.</p>
    </header>

    <div class="history">
        <h2>Our History</h2>
        <p>Founded in 1995, SouthVille 7 Credit Cooperative has grown from a small group of community members into a
            trusted financial institution serving thousands of members. Over the years, we have expanded our services
            and strengthened our commitment to member satisfaction and community development.</p>
    </div>

    <div class="mission-vision">
        <div class="mission">
            <h2>Our Mission</h2>
            <p>To provide quality financial services that meet the needs of our members while promoting economic growth
                and community development.</p>
        </div>
        <div class="vision">
            <h2>Our Vision</h2>
            <p>To be the leading credit cooperative recognized for excellence in service, innovation, and member
                satisfaction.</p>
        </div>
        <div class="values">
            <h2>Our Values</h2>
            <ul>
                <li><b>Integrity:</b> We uphold the highest standards of honesty and transparency in all our dealings.
                </li>
                <li><b>Member-Centric:</b> Our members are at the heart of everything we do.</li>
                <li><b>Community Focus:</b> We are committed to contributing to the well-being and development of our
                    community.</li>
                <li><b>Innovation:</b> We embrace change and continuously seek new ways to improve our services.</li>
                <li><b>Collaboration:</b> We believe in the power of working together to achieve common goals.</li>
            </ul>
        </div>
    </div>

    <div class="board">
        <h2>Board of Directors</h2>
        <div class="board-members">
            <div class="member-card">
                <img src="https://placehold.co/150x150" alt="Member Photo">
                <h3>John Doe</h3>
                <p>Chairperson</p>
            </div>
            <div class="member-card">
                <img src="https://placehold.co/150x150" alt="Member Photo">
                <h3>Jane Smith</h3>
                <p>Vice Chairperson</p>
            </div>
            <div class="member-card">
                <img src="https://placehold.co/150x150" alt="Member Photo">
                <h3>Michael Johnson</h3>
                <p>Treasurer</p>
            </div>
            <div class="member-card">
                <img src="https://placehold.co/150x150" alt="Member Photo">
                <h3>Emily Davis</h3>
                <p>Secretary</p>
            </div>
        </div>
    </div>
</body>

</html>
