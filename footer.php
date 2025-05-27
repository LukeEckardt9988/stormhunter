<?php
// footer.php
?>
</div> <footer class="mt-5 p-3 text-center bg-light">
    Bibel-App &copy; <?php echo date('Y'); ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const currentUserIdFromPHP = <?php echo json_encode($loggedInUserId ?? 0); ?>;
</script>
<script src="app.js"></script> </body>
</html>