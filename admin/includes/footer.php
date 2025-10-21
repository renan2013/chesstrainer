<div class="text-center mt-4">
            <a href="index.php">Volver al Panel de Administración</a>
        </div>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <img src="https://ajedrezpuriscal.com/chess_trainer/img/logo_ct.svg" width="200px"><br/>
            <span class="text-muted">developed by renangalvan.net - (+506) 87777849 - San José, Costa Rica. 2025</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>