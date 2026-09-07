<?php
/**
 * Local variables.
 *
 * @var string $terms_and_conditions_content
 */
?>

<div id="terms-and-conditions-modal" class="modal fade">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= lang('terms_and_conditions') ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <style>
                #terms-and-conditions-modal .modal-body {
                    font-family: var(--bs-body-font-family, inherit);
                    font-size: 1rem;
                    line-height: 1.6;
                    color: var(--bs-body-color, inherit);
                }
                #terms-and-conditions-modal .modal-body p,
                #terms-and-conditions-modal .modal-body li,
                #terms-and-conditions-modal .modal-body ul {
                    font-family: inherit;
                }
            </style>
            <div class="modal-body">
                <?= pure_html($terms_and_conditions_content) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= lang('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>
