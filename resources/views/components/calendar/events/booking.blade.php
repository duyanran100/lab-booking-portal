<div x-data="{ 
        formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        },
    }" 
    class="flex flex-col bg-danger"
    :class="bg-red-500">
    <div class="text-sm">
        <span x-text="formatTime(event.start)"></span> - 
        <span x-text="formatTime(event.end)"></span>
    </div>
    <span x-text="event.title"></span>
</div>
