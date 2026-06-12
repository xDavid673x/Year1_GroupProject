const filter = document.getElementById('exerciseFilter');
const exercises = document.querySelectorAll('.exercise');
const strengthFilter = document.getElementById('strengthFilter');

function filterExercises() {
    const selectedType = filter.value;
    const selectedGroup = strengthFilter.value;

    exercises.forEach(ex => {
        const typeMatch = (selectedType === 'All' || ex.dataset.type === selectedType);
        let groupMatch = true;

        if (selectedType === 'Strength' && strengthFilter.style.display === 'block') {
            groupMatch = (selectedGroup === 'All' || ex.dataset.group === selectedGroup);
        }

        if (typeMatch && groupMatch) {
            ex.style.display = 'block';
        } else {
            ex.style.display = 'none';
        }
    });

    strengthFilter.style.display = (selectedType === 'Strength') ? 'block' : 'none';
}

filter.addEventListener('change', filterExercises);
strengthFilter.addEventListener('change', filterExercises);

filterExercises();
