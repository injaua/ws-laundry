// Purchase order (resources/purchases/show): receiving posts to the picked line.
const line = document.getElementById('receive-line');
line?.addEventListener('change', () => {
    document.getElementById('receive-form').action = line.value;
});
