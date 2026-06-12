function openTab(event, idName){
    var i, tabcontent, tablinks;

    tabcontent = document.getElementsByClassName("tab-content")
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    document.getElementById(idName).style.display = "block";
    event.currentTarget.className += " active";

}

document.getElementById("default-open").click();


let pfp = document.getElementById("pfp");
let inputFile = document.getElementById("input-file");

inputFile.onchange = function(){
    pfp.src = URL.createObjectURL(inputFile.files[0]);
}

const weightInput = document.getElementById("weight");
const heightInput = document.getElementById("height");
const bmi_result = document.getElementById("bmi_result");

function calculate_bmi(weight, height){
    if (height <= 0 || weight <= 0){
        return { bmi: 0, category: "Invalid"};
    }

    const height_m = height / 100;
    const bmi = weight/ (height_m ** 2);
    let category;

    if (bmi < 18.5){
        category = "Underweight";
    }
    
    else if (bmi >= 18.5 && bmi < 25){
        category = "Normal";
    }
    
    else if (bmi >= 25 && bmi < 30){
        category = "Overweight";
    }

    else if (bmi >= 30){
        category = "Obese";
    }

    return {
        bmi: Math.round(bmi * 10) / 10,
        category: category
    };
}

function updateBMI() {
    const weight = parseFloat(weightInput.value);
    const height = parseFloat(heightInput.value);

    if (!weight || !height) {
        bmi_result.value = "";
        return;
    }

    const { bmi, category } = calculate_bmi(weight, height);
    bmi_result.value = "BMI: " + bmi + " (" + category + ")";
}

weightInput.addEventListener("input", updateBMI);
heightInput.addEventListener("input", updateBMI);

updateBMI(); 

const bioTextarea = document.getElementById('bio-textarea');

let bioTimeout;

bioTextarea.addEventListener('input', () => {
    clearTimeout(bioTimeout);

    // This waits 500ms after the user stops typing to avoid too many requests
    bioTimeout = setTimeout(() => {
        const bio = bioTextarea.value;

        const formData = new FormData();
        formData.append('bio', bio);

        fetch('update-bio.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to save bio:', data.error);
            }
        })
        .catch(err => console.error('Error saving bio:', err));
    }, 500);
});

const fileInput = document.getElementById('input-file');

fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('profilepic', file);

    fetch('update-pfp.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.profilepicURL) {
            // update the img src instantly
            document.getElementById('pfp').src = data.profilepicURL + '?t=' + new Date().getTime();
        } else if (!data.success) {
            alert('Error uploading profile picture: ' + data.error);
        }
    })
    .catch(err => console.error('Upload failed:', err));
});

document.getElementById('logout-btn').addEventListener('click', async function(e) {
    e.preventDefault();
    try {
        await fetch('../Login_FAQs/api/logout.php', { method: 'POST', credentials: 'include' });
    } catch {}
    sessionStorage.setItem('nav_auth_state_v1', 'out');
    window.location.href = '../Login_FAQs/login.html';
});