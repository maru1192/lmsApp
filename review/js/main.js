const r = document.querySelector('.achieve_bar .range');
const now = document.getElementById('rangeNow');
r.addEventListener('input', () => now.textContent = r.value + '%');

