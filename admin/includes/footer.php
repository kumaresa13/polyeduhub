</div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; PolyEduHub <?= date('Y') ?></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Admin JavaScript -->
    <script>
        // Toggle the side navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            if(sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    document.body.classList.toggle('sidebar-toggled');
                    document.querySelector('.sidebar').classList.toggle('toggled');
                });
            }
            
            // Sidebar toggle for mobile
            const sidebarToggleTop = document.getElementById('sidebarToggleTop');
            if(sidebarToggleTop) {
                sidebarToggleTop.addEventListener('click', function(e) {
                    document.querySelector('.sidebar').classList.toggle('toggled');
                });
            }
            
            // Close sidebar on small screens
            function checkWindowSize() {
                if (window.innerWidth < 768) {
                    document.querySelector('.sidebar').classList.add('toggled');
                }
            }
            
            window.addEventListener('resize', checkWindowSize);
            checkWindowSize(); // Check on page load
            
            // Scroll to top button visibility
            const scrollToTopButton = document.querySelector('.scroll-to-top');
            if(scrollToTopButton) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 100) {
                        scrollToTopButton.style.display = 'block';
                    } else {
                        scrollToTopButton.style.display = 'none';
                    }
                });
                
                // Scroll to top when button is clicked
                scrollToTopButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.scrollTo({top: 0, behavior: 'smooth'});
                });
            }
        });
    </script>
    
    <!-- Page specific JavaScript -->
    <?php if (isset($additional_js)): echo $additional_js; endif; ?>
</body>
</html>