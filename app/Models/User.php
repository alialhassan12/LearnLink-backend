<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'role', 'password', 'avatar','status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    // Relations
    public function student(){
        return $this->hasOne(Student::class);
    }

    public function teacher(){
        return $this->hasOne(Teacher::class);
    }

    public function conversations(){
        return $this->hasManyThrough(
            Conversation::class,
            ConversationParticipant::class,
            'user_id',
            'id',
            'id',
            'conversation_id'
        )->with('participants')->with('lastMessage')->orderBy('updated_at','desc');
    }

    public function subscription(){
        return $this->hasOne(Subscription::class);
    }

    // Casts
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
