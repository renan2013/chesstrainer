    <br/><br/>
    <footer class="footer mt-auto py-2 bg-light">
        <div class="container text-center">
             <img src="img/logo_ct.svg" alt="Chess Trainer Logo" style="width: 110px; height: auto;"><br/>
            <span class="text-muted" style="font-size: 0.75rem;">developed by renangalvan.net - (+506) 87777849 - San José, Costa Rica. <?php echo date('Y'); ?></span>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>
