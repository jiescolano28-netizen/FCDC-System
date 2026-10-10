<section class="profile-page" aria-labelledby="profile-heading">
    <header class="profile-heading">
        <div>
            <h1 id="profile-heading">My profile</h1>
            <p class="profile-subtitle">Manage your username and password, and review the activity you performed.</p>
        </div>
        <div class="profile-identity">
            <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($username, 0, 1)) }}</span>
            <span><strong>{{ $username }}</strong><small>{{ auth()->user()->email }}</small></span>
        </div>
    </header>

    @if (session('profile-status'))
        <p class="profile-status" role="status">{{ session('profile-status') }}</p>
    @endif

    <div class="profile-grid">
        <section class="profile-card" aria-labelledby="profile-username-heading">
            <header class="profile-card-heading">
                <h2 id="profile-username-heading">Username</h2>
                <p>Update the username used to identify your employee account.</p>
            </header>
            <form class="profile-form" wire:submit="updateUsername">
                <label for="profile-username">Username</label>
                <input id="profile-username" type="text" maxlength="255" autocomplete="username" wire:model="username" required @error('username') aria-invalid="true" aria-describedby="profile-username-error" @enderror>
                @error('username') <span class="profile-error" id="profile-username-error">{{ $message }}</span> @enderror
                <button class="profile-button" type="submit" wire:loading.attr="disabled" wire:target="updateUsername">Save username</button>
            </form>
        </section>

        <section class="profile-card" aria-labelledby="profile-password-heading">
            <header class="profile-card-heading">
                <h2 id="profile-password-heading">Change password</h2>
                <p>Confirm your current password before choosing a new one.</p>
            </header>
            <form class="profile-form" wire:submit="updatePassword">
                <label for="profile-current-password">Current password</label>
                <input id="profile-current-password" type="password" autocomplete="current-password" wire:model="currentPassword" required @error('currentPassword') aria-invalid="true" aria-describedby="profile-current-password-error" @enderror>
                @error('currentPassword') <span class="profile-error" id="profile-current-password-error">{{ $message }}</span> @enderror

                <label for="profile-new-password">New password</label>
                <input id="profile-new-password" type="password" minlength="8" autocomplete="new-password" wire:model="newPassword" required @error('newPassword') aria-invalid="true" aria-describedby="profile-new-password-error" @enderror>
                @error('newPassword') <span class="profile-error" id="profile-new-password-error">{{ $message }}</span> @enderror

                <label for="profile-new-password-confirmation">Confirm new password</label>
                <input id="profile-new-password-confirmation" type="password" minlength="8" autocomplete="new-password" wire:model="newPassword_confirmation" required>
                <button class="profile-button" type="submit" wire:loading.attr="disabled" wire:target="updatePassword">Update password</button>
            </form>
        </section>
    </div>

    <section class="profile-card profile-activity" aria-labelledby="profile-activity-heading">
        <header class="profile-card-heading profile-activity-heading">
            <div>
                <h2 id="profile-activity-heading">My activity</h2>
                <p>Recent audit events performed by your account.</p>
            </div>
            <span class="profile-activity-count">{{ number_format($activities->total()) }} events</span>
        </header>

        <div class="profile-activity-list">
            @forelse ($activities as $activity)
                @php
                    $subject = $activity->subject?->username
                        ?? ($activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : null);
                @endphp
                <article class="profile-activity-item">
                    <span class="profile-activity-marker" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $activity->description }}</strong>
                        @if ($subject)
                            <span>{{ $subject }}</span>
                        @endif
                    </div>
                    <time datetime="{{ $activity->created_at?->toISOString() }}">{{ $activity->created_at?->format('M d, Y · H:i') }}</time>
                </article>
            @empty
                <p class="profile-activity-empty">No activity has been recorded for your account yet.</p>
            @endforelse
        </div>

        @if ($activities->hasPages())
            <nav class="profile-pagination" aria-label="Personal activity pages">{{ $activities->links() }}</nav>
        @endif
    </section>
</section>
