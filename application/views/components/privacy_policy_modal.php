<?php
/**
 * Local variables.
 *
 * @var string $privacy_policy_content
 */
?>

<div id="privacy-policy-modal" class="modal fade">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= lang('privacy_policy') ?></h4>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <style>
                #privacy-policy-modal .modal-body {
                    font-family: var(--bs-body-font-family, inherit);
                    font-size: 1rem;
                    line-height: 1.6;
                    color: var(--bs-body-color, inherit);
                }
                #privacy-policy-modal .modal-body p,
                #privacy-policy-modal .modal-body li,
                #privacy-policy-modal .modal-body ul {
                    font-family: inherit;
                }
            </style>
            <div class="modal-body">
                <?= pure_html($privacy_policy_content) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= lang('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>
