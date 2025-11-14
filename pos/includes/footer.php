            </div>
        </main>
    </div>
    
    <?php if (isset($additional_js)): foreach ($additional_js as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
