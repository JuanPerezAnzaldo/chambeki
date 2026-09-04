    <div class="mensajePrueba" style="text-align: center; padding: 20px;">
        <h2>Soy una app para buscar los mejores servicios</h2>
    
        <button id="botonInstalar" style="display: none;background: rgb(43 127 57);color: #ffffff;border-width: medium;border-style: none;border-color: currentcolor;border-image: none;padding: 12px 24px;border-radius: 8px;font-size: 16px;font-weight: bold;cursor: pointer;margin-top: 20px;box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 6px;">
             Descargar Aplicación
        </button>
    </div>
    
    <script>
        let eventoInstalacion = null;
        const botonInstalar = document.getElementById('botonInstalar');
    
        //Verifica cuando el celular deja instalar la app y muestra el botón
        window.addEventListener('beforeinstallprompt', (evento) => 
        {
            evento.preventDefault();
            eventoInstalacion = evento;
            botonInstalar.style.display = 'inline-block';
        });
    
        //Abre la ventana de instalación al hacer click en el  botón
        botonInstalar.addEventListener('click', async () => 
        {
            if (!eventoInstalacion) return;
    
            eventoInstalacion.prompt();
            
            await eventoInstalacion.userChoice;
            
            eventoInstalacion = null;
            botonInstalar.style.display = 'none';
        });
    </script>