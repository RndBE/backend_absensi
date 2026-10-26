{{-- Isi pilihan "Untuk" tiap item biaya dari peserta yang dicentang di Tag Tim, dan perbarui saat
     centang berubah atau item ditambah. Pemilik yang dipilih tetap terpilih selama orangnya masih ditag. --}}
<script>
(function () {
    const form = document.getElementById('budgetRequestForm');
    const list = document.getElementById('budgetItems');
    if (!form || !list) return;

    function participants() {
        return Array.from(form.querySelectorAll('input[name="participants[]"]:checked')).map(function (box) {
            return { id: box.value, name: (box.closest('label')?.textContent || '').trim() };
        });
    }

    function refresh() {
        const people = participants();
        list.querySelectorAll('select[data-item-owner]').forEach(function (select) {
            const wanted = select.dataset.selected || '';
            select.innerHTML = '';
            select.add(new Option('Bersama / milik pengaju', ''));
            people.forEach(function (person) {
                select.add(new Option(person.name, person.id));
            });
            select.value = people.some(function (person) { return person.id === wanted; }) ? wanted : '';
        });
    }

    list.addEventListener('change', function (event) {
        if (event.target.matches('select[data-item-owner]')) {
            event.target.dataset.selected = event.target.value;
        }
    });
    form.addEventListener('change', function (event) {
        if (event.target.matches('input[name="participants[]"]')) refresh();
    });
    new MutationObserver(refresh).observe(list, { childList: true });

    refresh();
})();
</script>
