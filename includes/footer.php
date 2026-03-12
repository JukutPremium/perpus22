<?php if (isLoggedIn()): ?>
    </main>
</div>
<?php endif; ?>
<script>
// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(a => setTimeout(() => { a.style.opacity='0'; a.style.transition='opacity 0.5s'; setTimeout(()=>a.remove(),500); }, 4000));
});
// Confirm delete
function confirmDelete(msg) {
    return confirm(msg || 'Yakin ingin menghapus data ini?');
}
</script>
</body>
</html>
