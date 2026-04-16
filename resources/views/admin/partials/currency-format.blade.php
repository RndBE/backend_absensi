{{-- Currency Input Formatter --}}
{{-- Usage: add class "currency-input" and data-target="realFieldName" to text input --}}
{{-- A hidden input with name=realFieldName stores the raw numeric value --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    function formatRupiah(val) {
        let num = String(val).replace(/\D/g, '');
        if (!num) return '';
        return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function unformat(val) {
        return String(val).replace(/\./g, '');
    }

    document.querySelectorAll('.currency-input').forEach(function(el) {
        // Format on load
        let raw = el.value;
        if (raw) el.value = formatRupiah(raw);

        el.addEventListener('input', function() {
            let pos = this.selectionStart;
            let oldLen = this.value.length;
            let rawVal = unformat(this.value);
            this.value = formatRupiah(rawVal);
            let newLen = this.value.length;
            let newPos = pos + (newLen - oldLen);
            this.setSelectionRange(newPos, newPos);

            // Update hidden field
            let target = this.getAttribute('data-target');
            if (target) {
                let hidden = this.closest('form').querySelector('input[name="' + target + '"]');
                if (hidden) hidden.value = rawVal;
            }
        });

        el.addEventListener('focus', function() {
            if (this.value === '0') this.value = '';
        });

        el.addEventListener('blur', function() {
            if (!this.value) {
                this.value = '0';
                let target = this.getAttribute('data-target');
                if (target) {
                    let hidden = this.closest('form').querySelector('input[name="' + target + '"]');
                    if (hidden) hidden.value = '0';
                }
            }
        });
    });
});
</script>
