// JS for interactive premium meal selection
// Allows one meal per day/time, add/remove, updates hidden input for form submit

document.addEventListener('DOMContentLoaded', function() {
    const selectionState = window.initialPremiumSelection || {};
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const times = ['Breakfast','Lunch','Dinner'];

    // Add event listeners to all add buttons
    document.querySelectorAll('.add-meal-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const day = this.dataset.day;
            const time = this.dataset.time;
            const meal = this.dataset.meal;
            // Only one meal per slot
            selectionState[day] = selectionState[day] || {};
            selectionState[day][time] = meal;
            renderSelections();
        });
    });
    // Add event listeners to all remove buttons
    document.querySelectorAll('.remove-meal-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const day = this.dataset.day;
            const time = this.dataset.time;
            if (selectionState[day]) {
                delete selectionState[day][time];
                renderSelections();
            }
        });
    });

    function renderSelections() {
        // Update UI to show selected meal, hide add button, show remove button, etc.
        days.forEach(day => {
            times.forEach(time => {
                const mealVal = selectionState[day] && selectionState[day][time] ? selectionState[day][time] : null;
                const addBtn = document.querySelector(`.add-meal-btn[data-day='${day}'][data-time='${time}']`);
                const removeBtn = document.querySelector(`.remove-meal-btn[data-day='${day}'][data-time='${time}']`);
                const selectedSpan = document.querySelector(`.selected-meal[data-day='${day}'][data-time='${time}']`);
                if (mealVal) {
                    if (addBtn) addBtn.style.display = 'none';
                    if (removeBtn) removeBtn.style.display = 'inline-block';
                    if (selectedSpan) selectedSpan.textContent = mealVal;
                } else {
                    if (addBtn) addBtn.style.display = 'inline-block';
                    if (removeBtn) removeBtn.style.display = 'none';
                    if (selectedSpan) selectedSpan.textContent = '';
                }
            });
        });
        // Update hidden input for form submit
        document.getElementById('premiumMealSelection').value = JSON.stringify(selectionState);
    }

    renderSelections();
});
