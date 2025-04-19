</div>
        <!-- End Page Content -->
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto py-3">
                    <span>&copy; <?= date('Y') ?> PolyEduHub. All rights reserved.</span>
                </div>
            </div>
        </footer>
    </div>
    <!-- End Main Content -->
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Student JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            if(sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const sidebarBtn = document.getElementById('sidebarToggle');
                
                if (window.innerWidth < 768 && 
                    sidebar.classList.contains('show') && 
                    !sidebar.contains(event.target) && 
                    event.target !== sidebarBtn) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
    
    <!-- Additional Page-Specific Scripts -->
    <?= isset($additional_scripts) ? $additional_scripts : '' ?>
</body>
</html>