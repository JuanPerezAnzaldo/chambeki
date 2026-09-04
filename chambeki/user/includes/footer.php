    </main>
        <footer>
            <p>&copy; Servicio Verde</p>
        </footer>
    
        <!--Service Worker-->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => 
                {
                    navigator.serviceWorker.register('/sw.js').then((reg) => console.log('Service Worker registrado correctamente', reg)).catch((err) => console.log('Error al registrar el Service Worker', err));
                });
            }
        </script>
    </body>
</html>