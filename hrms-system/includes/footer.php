    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Update clock every second
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Initial update
        updateClock();
        setInterval(updateClock, 1000);
        
        // Load pending leave count for admin
        <?php if($user_role == 'admin' || $user_role == 'hr'): ?>
        fetch('../admin/get_pending_count.php')
            .then(response => response.json())
            .then(data => {
                if(data.count > 0) {
                    document.getElementById('pendingBadge').textContent = data.count;
                    document.getElementById('pendingBadge').classList.add('badge-pulse');
                }
            });
        <?php endif; ?>
        
        // Profile picture dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            userMenu.addEventListener('click', function(e) {
                this.querySelector('.dropdown').classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userMenu.contains(e.target)) {
                    userMenu.querySelector('.dropdown').classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>