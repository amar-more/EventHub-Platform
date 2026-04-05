// DOM Elements

const themeToggle = document.getElementById('themeToggle');
const tabButtons = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');
const favoriteButtons = document.querySelectorAll('.favorite-btn');

// Theme Toggle

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    const scrollLeft = document.getElementById('scrollLeft');
    const scrollRight = document.getElementById('scrollRight');
    const weeklyEvents = document.getElementById('weeklyEvents');

    const savedTheme = localStorage.getItem('theme');

    // Apply saved theme or default to system preference
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }

    // Theme toggle event listener
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    }

    // Weekly Events Scroll
    if (scrollLeft && scrollRight && weeklyEvents) {
        scrollLeft.addEventListener('click', () => {
            weeklyEvents.scrollBy({ left: -300, behavior: 'smooth' });
        });
        scrollRight.addEventListener('click', () => {
            weeklyEvents.scrollBy({ left: 300, behavior: 'smooth' });
        });
    }

    // Mobile menu toggle
    const mobileMenuButton = document.querySelector('.mobile-menu-button');
    const mobileMenu = document.querySelector('.mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }
});

// Tabs

tabButtons.forEach(button => {
    button.addEventListener('click', () => {
        // Remove active class from all buttons and contents
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));

        // Add active class to clicked button and corresponding content
        button.classList.add('active');
        const tabId = button.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// Create Event Card

function createEventCard(event) {
    const eventCard = document.createElement('div');
    eventCard.className = 'event-card';
    eventCard.setAttribute('data-type', event.event_type);

    const eventImage = document.createElement('div');
    eventImage.className = 'event-image';
    eventImage.innerHTML = `
        <img src='Event_image/${event.image}' alt='Event Image'>
        <div class='image-overlay'></div>
        <div class='card-badge'>${event.event_type}</div>`;

    const eventDetails = document.createElement('div');
    eventDetails.className = 'event-details';
    eventDetails.innerHTML = `
        <h3 class='event-title'>${event.title}</h3>
        <p class='event-description'>${event.description}</p>
        <div class='event-info'>
            <p><i class='far fa-calendar'></i> ${event.event_date}</p>
            <p><i class='far fa-map-marker-alt'></i> ${event.department}</p>
            <p><i class='fas fa-map-marker-alt'></i> ${event.college_name}</p>
        </div>
        <a href='${event.referencelink}' class='register-btn'>Register Now</a>`;

    eventCard.appendChild(eventImage);
    eventCard.appendChild(eventDetails);

    return eventCard;
}

// Filter Events Function

function filterEvents(category) {
    const targetTabContent = document.getElementById(category);
    if (!targetTabContent) return;

    targetTabContent.innerHTML = '';
    const eventsGrid = document.createElement('div');
    eventsGrid.className = 'events-grid';
    targetTabContent.appendChild(eventsGrid);

    allEventsData.forEach(event => {
        if (category === 'all' || event.event_type.toLowerCase() === category) {
            const eventCard = createEventCard(event);
            eventsGrid.appendChild(eventCard);
        }
    });
}

// Initialize filters

filterEvents();

// Search functionality (placeholder)

const searchInputs = document.querySelectorAll('.search-input, .search-input-small');

searchInputs.forEach(input => {
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            alert(`Searching for: ${input.value}`);
            input.value = '';
        }
    });
});
