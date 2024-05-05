<input type='text' name='<?php echo $data['attributes']['name']; ?>' class='force-hide'>

<div class="fluentform-signature-pad-wrapper">
    <canvas id='<?php echo $data['attributes']['name'].'_'.$form->id; ?>' 
            class='fluentform-signature-pad' 
            data-form-id='<?php echo $form->id; ?>'
            data-pen-color='<?php echo $data['settings']['sign_pen_color'] ; ?>'
            data-pen-size='<?php echo $data['settings']['sign_pen_size'] ; ?>'
            style='
                background-color: <?php echo $data['settings']['sign_background_color']; ?>;
                border: 2px dashed <?php echo $data['settings']['sign_border_color']; ?>;
                width: fit-content;
            '
            height="<?php echo $data['settings']['sign_pad_height']; ?>"
    ></canvas>

    <div class="ff-el-signature__actions">
        <div class='fluentform-signature-pad-actions'>
            <button type='button' class='fluentform-signature-button fluentform-signature-clear'>
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 561 561" xml:space="preserve"><g><g id="loop"><path d="M280.5,76.5V0l-102,102l102,102v-76.5c84.15,0,153,68.85,153,153c0,25.5-7.65,51-17.85,71.4l38.25,38.25C471.75,357,484.5,321.3,484.5,280.5C484.5,168.3,392.7,76.5,280.5,76.5z M280.5,433.5c-84.15,0-153-68.85-153-153c0-25.5,7.65-51,17.85-71.4l-38.25-38.25C89.25,204,76.5,239.7,76.5,280.5c0,112.2,91.8,204,204,204V561l102-102l-102-102V433.5z"/></g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g></svg>
            </button>
            
            <button type='button' class='fluentform-signature-button fluentform-signature-undo'>
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 497.25 497.25" xml:space="preserve"><g><g id="undo"><path d="M248.625,89.25V0l-127.5,127.5l127.5,127.5V140.25c84.15,0,153,68.85,153,153c0,84.15-68.85,153-153,153c-84.15,0-153-68.85-153-153h-51c0,112.2,91.8,204,204,204s204-91.8,204-204S360.825,89.25,248.625,89.25z"/></g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g></svg>
            </button>

            <button type='button' class='fluentform-signature-button fluentform-signature-redo'>
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 485.212 485.212" xml:space="preserve"><g><path d="M242.607,424.559c-75.252,0-136.468-61.209-136.468-136.465c0-75.252,61.216-136.466,136.468-136.466v90.978l151.629-121.302L242.607,0v90.978c-108.687,0-197.117,88.432-197.117,197.117c0,108.691,88.43,197.118,197.117,197.118c108.687,0,197.114-88.427,197.114-197.118h-60.645C379.077,363.35,317.859,424.559,242.607,424.559z"/></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g></svg>
            </button>
        </div>

        <div class='ff-el-signature__actions-hint fluentform-signature-hint'><?php echo $data['settings']['sign_instruction']; ?></div>
    </div>
</div>
