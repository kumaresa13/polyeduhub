</div>
        <!-- End Page Content -->
        
        <!-- Footer -->
        <footer class="copyright">
            <div class="text-end">
                &copy; <?= date('Y') ?> PolyEduHub. All rights reserved.
            </div>
        </footer>
    </div>
    <!-- End Main Content -->
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Font Awesome 6 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Admin JS -->
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