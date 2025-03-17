<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    &copy; <?= date('Y') ?> <?= APP_NAME ?> Student Portal
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="../logout.php" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom Student JS -->
    <script src="../assets/js/student.js"></script>
    
    <!-- Additional Page-Specific Scripts -->
    <?= $additional_scripts ?? '' ?>
</body>
</html>