<div class="<?= $outerClass ?? 'flex-grow relative flex items-center' ?>">
    <div class="flex w-full items-center gap-1" <?= !empty($todayButton) ? 'x-data="{ setToday() { const today = new Date(); const month = (\'0\' + (today.getMonth() + 1)).slice(-2); const day = (\'0\' + today.getDate()).slice(-2); this.$refs.dateInput.value = today.getFullYear() + \'-\' + month + \'-\' + day; this.$refs.dateInput.dispatchEvent(new Event(\'input\', { bubbles: true })); this.$refs.dateInput.dispatchEvent(new Event(\'change\', { bubbles: true })); } }"' : '' ?>>
        <input type="date" <?= $attributes; ?> maxlength="10"
        <?= !empty($todayButton) ? 'x-ref="dateInput"' : '' ?>
        class="<?= $groupClass; ?> flex-1 min-w-0 rounded-md font-sans py-2 text-gray-900 placeholder:text-gray-500 focus:ring-1 focus:ring-inset focus:ring-blue-500 sm:text-sm sm:leading-5"
        />
        <?php if (!empty($todayButton)) { ?>
            <button type="button" x-on:click="setToday()" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-md border border-gray-400 bg-white px-3 py-2 text-xs font-medium text-gray-600 hover:border-blue-700 hover:bg-blue-200 hover:text-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 disabled:cursor-not-allowed disabled:opacity-50" aria-label="<?= __('Today') ?>" title="<?= __('Today') ?>" <?= !empty($readonly) ? 'disabled' : '' ?>><?= __('Today') ?></button>
        <?php } ?>
    </div>
</div><?php
?>
