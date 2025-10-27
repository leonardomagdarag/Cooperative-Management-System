<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite('resources/css/public/events.css')
    <title>Events</title>
</head>

<body>
    @extends('layouts.app')
    <header>
        <h1>Events</h1>
        <p>Stay updated with the latest events and activities organized by SouthVille 7 Credit Cooperative. We are
            committed to fostering community engagement and providing opportunities for our members to connect and grow.
        </p>
    </header>

    <div class="upcoming-events">
        <h2>Upcoming Events</h2>
        <hr>
        <div class="event-cards">
            <div class="event-card">
                <img src="https://placehold.co/300x200" alt="Event Image">
                <h3>Financial Literacy Workshop</h3>
                <p>Date: July 15, 2024</p>
                <p>Join us for an informative workshop on financial literacy, covering budgeting, saving, and investing
                    tips to help you manage your finances effectively.</p>
            </div>
            <div class="event-card">
                <img src="https://placehold.co/300x200" alt="Event Image">
                <h3>Annual Member Meeting</h3>
                <p>Date: August 10, 2024</p>
                <p>All members are invited to attend our Annual Member Meeting to discuss the cooperative's performance,
                    future plans, and to vote on important matters.</p>
            </div>
            <div class="event-card">
                <img src="https://placehold.co/300x200" alt="Event Image">
                <h3>Community Outreach Program</h3>
                <p>Date: September 5, 2024</p>
                <p>Participate in our community outreach program aimed at supporting local initiatives and giving back
                    to the community we serve.</p>
            </div>
        </div>
    </div>

    <div class="previous-events">
        <h2>Previous Events</h2>
        <div class="event-cards">
            <div class="event-card">
                <img src="https://placehold.co/300x200" alt="Event Image">
                <h3>Member Appreciation Day</h3>
                <p>Date: May 20, 2024</p>
                <p>We celebrated our members with a day full of fun activities, giveaways, and refreshments to show our
                    appreciation for their continued support.</p>
            </div>
            <div class="event-card">
                <img src="https://placehold.co/300x200" alt="Event Image">
                <h3>Spring Savings Campaign</h3>
                <p>Date: April 1-30, 2024</p>
                <p>Our Spring Savings Campaign encouraged members to save more with special incentives and rewards for
                    reaching their savings goals.</p>

            </div>
        </div>
    </div>
</body>

</html>
