<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FavoritedAuthorPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $author,
        protected Post $post
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->author->name} just posted: {$this->post->title}")
            ->greeting("Hey {$notifiable->name},")
            ->line("{$this->author->name} just published a new post on Chipper.")
            ->line($this->post->title)
            ->line(str($this->post->body)->limit(160))
            ->action('View post', url("/posts/{$this->post->id}"))
            ->line('Stay tuned for more updates from your favorites!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'author_id' => $this->author->id,
            'post_id' => $this->post->id,
            'title' => $this->post->title,
        ];
    }
}
