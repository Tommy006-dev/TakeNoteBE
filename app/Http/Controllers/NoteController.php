<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class NoteController extends Controller
{
    // 1. Lấy danh sách ghi chú (kèm theo các nhãn đã gắn)
    public function index(Request $request)
    {
        $user = $request->user();

        $myNotes = Note::where('user_id', $user->id)
            ->with(['labels', 'sharedUsers']) // Load sẵn nhãn và người được chia sẻ
            ->get()
            ->map(function ($note) {
                $note->is_owner = true;
                $note->shared_with = $this->formatSharedUsers($note->sharedUsers);
                return $note;
            });

        $sharedNotes = $user->sharedNotes()
            ->with(['labels', 'owner']) // Load nhãn và thông tin Chủ sở hữu
            ->get()
            ->map(function ($note) {
                $note->is_owner = false;
                $note->owner_name = $note->owner->name;
                $note->owner_email = $note->owner->email;
                $note->permission = $note->pivot->permission;
                $note->shared_at = $note->pivot->created_at;
                $note->shared_with = []; // Không cần cho xem những người khác
                return $note;
            });

        // Gộp chung 2 loại và sắp xếp mới nhất lên đầu
        $allNotes = $myNotes->merge($sharedNotes)->sortByDesc('updated_at')->values();

        return response()->json([
            'success' => true,
            'data' => $allNotes
        ]);
    }

    // 2. Tạo ghi chú mới trống hoặc có dữ liệu ban đầu 
    public function store(Request $request)
    {
        // FIX: Dùng filled() để tránh nhận null cho color (cột NOT NULL)
        $note = $request->user()->notes()->create([
            'title' => $request->input('title', ''),
            'content' => $request->input('content', ''),
            'color' => $request->filled('color') ? $request->input('color') : '#ffff',
            'is_pinned' => (bool) $request->input('is_pinned', false),
            'password' => $request->filled('password') ? Hash::make($request->password) : null,
        ]);

        // FIX: Lưu images ngay khi tạo note (trước đây chỉ update mới lưu được)
        if ($request->has('images')) {
            $note->images = $request->input('images', []);
            $note->save();
        }

        if ($request->has('labels')) {
            $note->labels()->sync($request->labels);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu ghi chú',
            'data' => $note->load('labels')
        ], 201);
    }

    // 3. Xem chi tiết 1 ghi chú cụ thể
    public function show(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không có quyền xem'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $note->load('labels')
        ], 200);
    }

    public function setupPassword(Request $request, $id)
    {
        $note = Note::where('user_id', auth()->id())->findOrFail($id);

        if (!empty($note->password)) {
            if (empty($request->old_password) || !Hash::check($request->old_password, $note->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu hiện tại không chính xác!'
                ], 400); 
            }
        }

        $newPassword = $request->input('new_password');
        $note->password = !empty($newPassword) ? Hash::make($newPassword) : null; // Mã hóa trước khi lưu
        $note->save();

        return response()->json([
            'success' => true,
            'message' => empty($newPassword) ? 'Đã gỡ bỏ bảo mật ghi chú!' : 'Cài đặt mật khẩu ghi chú thành công!'
        ]);
    }

    // 2. API Mở khóa ghi chú bằng cách đối chiếu Hash
    public function unlockNote(Request $request, $id)
    {
        $note = Note::findOrFail($id);
        $inputPassword = $request->input('password');

        if (!Hash::check($inputPassword, $note->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu truy cập không chính xác!'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mở khóa thành công!',
            'data' => $note
        ]);
    }

    // 4. Cập nhật ghi chú (Phục vụ hoàn hảo cho Auto-Save liên tục từ React)
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $note = Note::where(function($query) use ($user) {
            $query->where('user_id', $user->id) // Trường hợp 1: Là chủ sở hữu
                ->orWhereHas('sharedUsers', function($q) use ($user) { // Trường hợp 2: Là người được chia sẻ
                    $q->where('user_id', $user->id)
                        ->where('permission', 'edit'); // Phải có quyền được chỉnh sửa
                });
        })->findOrFail($id);

        $note->title = $request->input('title', $note->title);
        $note->content = $request->input('content', $note->content);
        $note->color = $request->input('color', $note->color);
        if ($request->has('is_pinned')) {
            $note->is_pinned = (bool) $request->input('is_pinned');
        }
        
        if ($request->has('images')) {
            $note->images = $request->images;
        }

        if ($request->has('labels')) {
            $note->labels()->sync($request->labels);
        }

        $note->save();

        broadcast(new \App\Events\NoteUpdated($note))->toOthers();

        return response()->json([
            'success' => true,
            'data' => $note->load('labels')
        ]);
    }

    // 5. Xóa ghi chú
    public function destroy(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không có quyền xóa'], 403);
        }

        $note->labels()->detach();
        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa ghi chú thành công'
        ], 200);
    }

    // 6. Tính năng nâng cao: Xác thực mật khẩu riêng của Ghi chú cụ thể
    public function verifyPassword(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không hợp lệ'], 403);
        }

        $request->validate([
            'password' => 'required|string'
        ]);

        // Kiểm tra xem mật khẩu gửi lên từ Dialog có khớp mật khẩu Bcrypt trong DB không
        if (Hash::check($request->password, $note->password)) {
            return response()->json([
                'success' => true,
                'message' => 'Mở khóa thành công',
                'data' => $note->load('labels') // Trả về full data ghi chú sau khi mở khóa thành công
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Mật khẩu bảo mật ghi chú không chính xác!'
        ], 401);
    }

    // Hàm phụ: Format lại mảng người được chia sẻ gửi cho React
    private function formatSharedUsers($users)
    {
        return $users->map(function ($u) {
            return [
                'email' => $u->email,
                'permission' => $u->pivot->permission,
                'sharedAt' => $u->pivot->created_at
            ];
        });
    }

    // 7. Gửi lời mời chia sẻ
    public function shareNote(Request $request, $id)
    {
        $request->validate(['email' => 'required|email', 'permission' => 'required|string']);
        
        $note = Note::where('user_id', auth()->id())->findOrFail($id); // Phải là chủ mới được chia sẻ
        $targetUser = \App\Models\User::where('email', $request->email)->first();

        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'Email chưa đăng ký tài khoản TakeNote!'], 404);
        }
        if ($targetUser->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Không thể tự chia sẻ cho chính mình!'], 400);
        }

        $note->sharedUsers()->syncWithoutDetaching([
            $targetUser->id => ['permission' => $request->permission]
        ]);

        return response()->json([
            'success' => true,
            'sharedWith' => $this->formatSharedUsers($note->sharedUsers()->get())
        ]);
    }

    // 8. Đổi quyền (Từ Chỉ xem -> Được sửa và ngược lại)
    public function updateShare(Request $request, $id)
    {
        $note = Note::where('user_id', auth()->id())->findOrFail($id);
        $targetUser = \App\Models\User::where('email', $request->email)->first();

        if ($targetUser) {
            $note->sharedUsers()->updateExistingPivot($targetUser->id, [
                'permission' => $request->permission
            ]);
        }

        return response()->json([
            'success' => true,
            'sharedWith' => $this->formatSharedUsers($note->sharedUsers()->get())
        ]);
    }

    // 9. Thu hồi quyền truy cập (Xóa khỏi danh sách)
    public function revokeShare(Request $request, $id)
    {
        $note = Note::where('user_id', auth()->id())->findOrFail($id);
        $targetUser = \App\Models\User::where('email', $request->email)->first();

        if ($targetUser) {
            $note->sharedUsers()->detach($targetUser->id);
        }

        return response()->json([
            'success' => true,
            'sharedWith' => $this->formatSharedUsers($note->sharedUsers()->get())
        ]);
    }
}