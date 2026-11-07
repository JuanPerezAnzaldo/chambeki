<?php
/*
    Cierre del layout abierto en header.php.
    En la pantalla de login se carga ademas el JS del ojito de contrasena
    que ya usa el sitio (autenticacion.js).
*/
?>
<?php if (($layoutAdmin ?? 'panel') === 'panel'): ?>

        </main>
    </div>

    <?php foreach (($scriptsExtra ?? []) as $rutaScript): ?>
        <script src="<?php echo escaparSalida($rutaScript); ?>"></script>
    <?php endforeach; ?>

<?php else: ?>

    </main>
    <script src="<?php echo JS_RUTA; ?>autenticacion.js?v=1"></script>

<?php endif; ?>

</body>
</html>
