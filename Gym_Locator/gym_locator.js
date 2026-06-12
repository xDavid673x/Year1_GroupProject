lucide.createIcons();

var map = L.map('map').setView([53.4808, -2.2426], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
}).addTo(map);

const gyms = [
    { name: "The Gym Group Manchester", lat: 53.4795, lng: -2.2383 },
    { name: "JD Gyms Manchester",        lat: 53.4830, lng: -2.2230 },
    { name: "PureGym Urban Exchange",    lat: 53.4839, lng: -2.2285 },
    { name: "Nuffield Health",           lat: 53.4780, lng: -2.2400 }
];

gyms.forEach(gym => {
    const safeId = gym.name.replace(/\s+/g, '-').replace(/[^a-zA-Z0-9-]/g, '');

    const popupContent = `
        <div style="text-align:center; min-width:170px;">
            <b>${gym.name}</b><br>
            <small>Open 24/7</small><br><br>
            <button onclick="selectGym('${gym.name.replace(/'/g, "\\'")}', '${safeId}')"
                style="background:#69a3e6; color:white; border:none;
                       padding:7px 16px; border-radius:20px; cursor:pointer; font-size:13px;">
                📍 Select This Gym
            </button>
            <p id="confirm-${safeId}"
               style="color:green; font-size:12px; margin-top:6px; display:none;">
                ✅ Gym saved to your profile!
            </p>
        </div>
    `;

    L.marker([gym.lat, gym.lng]).addTo(map).bindPopup(popupContent);
});

setTimeout(function() {
    map.invalidateSize();
}, 500);

async function selectGym(gymName, safeId) {
    try {
        const response = await fetch('save_gym.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gym: gymName })
        });

        const result = await response.json();

        if (result.success) {
            const confirmEl = document.getElementById('confirm-' + safeId);
            if (confirmEl) confirmEl.style.display = 'block';
        } else {
            alert('Could not save gym: ' + result.message);
        }
    } catch (err) {
        console.error('Error saving gym:', err);
        alert('Something went wrong. Please try again.');
    }
}

function toggleMenu() {
    document.getElementById('menu-options').classList.toggle('hidden');
}

function findGym() {
    const postcode = document.getElementById('postcodeInput').value.trim();
    const errorMsg = document.getElementById('error-msg');
    if (postcode) {
        errorMsg.classList.add('hidden');
        const query = encodeURIComponent(postcode + " gym");
        window.open(`https://www.google.com/maps/search/${query}`, '_blank');
    } else {
        errorMsg.classList.remove('hidden');
    }
}

document.getElementById('postcodeInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') findGym();
});
