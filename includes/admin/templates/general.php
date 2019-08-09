<form id="wpes_form_settings" action="options.php" method="POST" class="form_settings">
    <?php
        settings_fields( 'p-settings' );
        do_settings_sections( 'p_general_settings' );
        submit_button();
    ?>
</form>