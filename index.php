<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db.php';
 
// API Handler
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
 
    try {
        if ($action === 'login') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$input['q']]);
            $user = $stmt->fetch();
            if ($user && $user['password'] === $input['password']) {
                unset($user['password']);
                $_SESSION['user'] = $user;
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            }
        } elseif ($action === 'signup') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$input['username']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Username taken']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, username, password, bio, avatar, cover, friends, mutuals) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$input['name'], $input['username'], $input['password'], $input['bio'], strtoupper(substr($input['name'],0,1)), "#1a3a5c", "[]", "[]"]);
                $uid = $pdo->lastInsertId();
                $_SESSION['user'] = ['id'=>$uid, 'name'=>$input['name'], 'username'=>$input['username'], 'bio'=>$input['bio'], 'avatar'=>strtoupper(substr($input['name'],0,1)), 'cover'=>"#1a3a5c", 'friends'=>[], 'mutuals'=>[]];
                echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
            }
        } elseif ($action === 'get_data') {
            $users = $pdo->query("SELECT * FROM users")->fetchAll();
            $posts = $pdo->query("SELECT * FROM posts ORDER BY id DESC")->fetchAll();
            $requests = $pdo->query("SELECT * FROM requests")->fetchAll();
            $msgs = $pdo->query("SELECT * FROM messages")->fetchAll();
            $messages = [];
            foreach($msgs as $m) {
                if(!isset($messages[$m['chatKey']])) $messages[$m['chatKey']] = [];
                $messages[$m['chatKey']][] = ['from'=>$m['fromId'], 'text'=>$m['text'], 'time'=>$m['time']];
            }
            foreach($users as &$u) {
                $u['friends'] = json_decode($u['friends'] ?? '[]', true);
                $u['mutuals'] = json_decode($u['mutuals'] ?? '[]', true);
            }
            foreach($posts as &$p) {
                $p['likes'] = json_decode($p['likes'] ?? '[]', true);
                $p['comments'] = json_decode($p['comments'] ?? '[]', true);
            }
            echo json_encode(['users'=>$users, 'posts'=>$posts, 'requests'=>$requests, 'messages'=>$messages, 'session'=>$_SESSION['user']??null]);
        } elseif ($action === 'post') {
            $stmt = $pdo->prepare("INSERT INTO posts (userId, text, image, likes, comments, shares, time) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$input['userId'], $input['text'], $input['image'], "[]", "[]", 0, "just now"]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'like') {
            $stmt = $pdo->prepare("SELECT likes FROM posts WHERE id = ?");
            $stmt->execute([$input['postId']]);
            $likes = json_decode($stmt->fetchColumn(), true);
            $idx = array_search($input['userId'], $likes);
            if ($idx !== false) unset($likes[$idx]); else $likes[] = $input['userId'];
            $pdo->prepare("UPDATE posts SET likes = ? WHERE id = ?")->execute([json_encode(array_values($likes)), $input['postId']]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'comment') {
            $stmt = $pdo->prepare("SELECT comments FROM posts WHERE id = ?");
            $stmt->execute([$input['postId']]);
            $comments = json_decode($stmt->fetchColumn(), true);
            $comments[] = ['id'=>time(), 'userId'=>$input['userId'], 'text'=>$input['text'], 'time'=>'just now'];
            $pdo->prepare("UPDATE posts SET comments = ? WHERE id = ?")->execute([json_encode($comments), $input['postId']]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'friend_request') {
            $pdo->prepare("INSERT INTO requests (fromId, toId) VALUES (?,?)")->execute([$input['from'], $input['to']]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'accept_request') {
            $pdo->prepare("DELETE FROM requests WHERE fromId=? AND toId=?")->execute([$input['from'], $input['to']]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'send_message') {
            $key = implode('-', [min($input['from'], $input['to']), max($input['from'], $input['to'])]);
            $pdo->prepare("INSERT INTO messages (chatKey, fromId, text, time) VALUES (?,?,?,?)")->execute([$key, $input['from'], $input['text'], 'just now']);
            echo json_encode(['success' => true]);
        } elseif ($action === 'update_profile') {
            $pdo->prepare("UPDATE users SET name=?, bio=?, photo=? WHERE id=?")->execute([$input['name'], $input['bio'], $input['photo'], $input['id']]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete_post') {
            $pdo->prepare("DELETE FROM posts WHERE id=? AND userId=?")->execute([$input['postId'], $input['userId']]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Facebook Clone</title>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet" />
    <style>
        @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #1a2332; } ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        input, textarea, button { font-family: 'DM Sans', sans-serif; }
        .hover-bg:hover { background: rgba(255,255,255,0.06) !important; }
        .btn-primary { background: #1877f2 !important; color: #fff !important; border: none !important; cursor: pointer; transition: all 0.2s !important; }
        .btn-primary:hover { background: #1464d0 !important; transform: translateY(-1px); }
        .btn-ghost { background: transparent !important; border: 1px solid #374151 !important; color: #e7e9ea !important; cursor: pointer; transition: all 0.2s !important; }
        .btn-ghost:hover { background: rgba(255,255,255,0.06) !important; }
        .post-card { animation: fadeUp 0.4s ease both; }
    </style>
</head>
<body>
    <div id="root"></div>
    <script type="text/babel">
        const { useState, useEffect, useRef } = React;
 
        // ── Helpers ────────────────────────────────────────────────────────────────
        const avatarColors = ["#e85d04", "#7209b7", "#0077b6", "#2d6a4f", "#c1121f", "#6930c3"];
        const getColor = (id) => avatarColors[(id || 1) % avatarColors.length];
        const getInitials = (name) => (name || "?").split(" ").map(n => n[0]).join("").toUpperCase().slice(0, 2);
        const msgKey = (a, b) => [Math.min(a, b), Math.max(a, b)].join("-");
 
        // ── API Helper ─────────────────────────────────────────────────────────────
        const api = async (action, data = {}) => {
            try {
                const res = await fetch(`?api=1&action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                return await res.json();
            } catch (err) {
                console.error('API Error:', err);
                return { success: false, error: err.message };
            }
        };
 
        // ── Avatar Component ───────────────────────────────────────────────────────
        function Avatar({ user, size = 40, online = false }) {
            return (
                <div style={{ position: "relative", display: "inline-block", flexShrink: 0 }}>
                    {user?.photo ? (
                        <img src={user.photo} alt={user.name}
                            style={{ width: size, height: size, borderRadius: "50%", objectFit: "cover", border: "2px solid rgba(255,255,255,0.15)", display: "block" }} />
                    ) : (
                        <div style={{
                            width: size, height: size, borderRadius: "50%",
                            background: getColor(user?.id),
                            display: "flex", alignItems: "center", justifyContent: "center",
                            color: "#fff", fontWeight: 700, fontSize: size * 0.38,
                            fontFamily: "'DM Sans', sans-serif", flexShrink: 0,
                            border: "2px solid rgba(255,255,255,0.15)"
                        }}>
                            {getInitials(user?.name)}
                        </div>
                    )}
                    {online && (
                        <div style={{
                            position: "absolute", bottom: 1, right: 1,
                            width: size * 0.28, height: size * 0.28,
                            background: "#22c55e", borderRadius: "50%",
                            border: "2px solid #0f1419"
                        }} />
                    )}
                </div>
            );
        }
 
        // ── Input Field Component ──────────────────────────────────────────────────
        function InputField({ label, value, onChange, placeholder, type = "text" }) {
            return (
                <div style={{ marginBottom: 16 }}>
                    <label style={{ display: "block", fontSize: 12, fontWeight: 600, color: "#9ca3af", marginBottom: 6, letterSpacing: 0.5 }}>{label.toUpperCase()}</label>
                    <input type={type} value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
                        style={{ width: "100%", padding: "11px 14px", borderRadius: 10, background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.1)", color: "#e7e9ea", fontSize: 14, outline: "none", transition: "border-color 0.2s" }}
                        onFocus={e => e.target.style.borderColor = "#1877f2"}
                        onBlur={e => e.target.style.borderColor = "rgba(255,255,255,0.1)"} />
                </div>
            );
        }
 
        // ── Main App ───────────────────────────────────────────────────────────────
        function App() {
            const [currentUser, setCurrentUser] = useState(null);
            const [view, setView] = useState("login");
            const [appView, setAppView] = useState("feed");
            const [users, setUsers] = useState([]);
            const [posts, setPosts] = useState([]);
            const [messages, setMessages] = useState({});
            const [friendRequests, setFriendRequests] = useState([]);
            const [activeChat, setActiveChat] = useState(null);
            const [profileView, setProfileView] = useState(null);
            const [notification, setNotification] = useState(null);
            const [loading, setLoading] = useState(true);
 
            const showNotif = (msg, type = "success") => {
                setNotification({ msg, type });
                setTimeout(() => setNotification(null), 3000);
            };
 
            const loadData = async () => {
                try {
                    const data = await api('get_data');
                    console.log('Loaded data:', data);
                    if (data.session) setCurrentUser(data.session);
                    setUsers(data.users || []);
                    setPosts(data.posts || []);
                    setMessages(data.messages || {});
                    setFriendRequests(data.requests || []);
                } catch (err) {
                    console.error('Load error:', err);
                }
                setLoading(false);
            };
 
            useEffect(() => { loadData(); }, []);
 
            useEffect(() => {
                if (currentUser) {
                    const fresh = users.find(u => u.id === currentUser.id);
                    if (fresh && fresh.photo !== currentUser.photo) {
                        setCurrentUser(fresh);
                    }
                }
            }, [users]);
 
            if (loading) {
                return <div style={{ padding: 50, textAlign: 'center', color: '#fff', fontSize: 18 }}>Loading Facebook...</div>;
            }
 
            if (!currentUser || view === "login") {
                return <AuthScreen users={users} setUsers={setUsers} setCurrentUser={setCurrentUser} setView={setView} view={view} />;
            }
 
            const myFriends = users.filter(u => currentUser.friends.includes(u.id));
            const pendingRequests = friendRequests.filter(r => r.to === currentUser.id);
 
            const handleLike = (postId) => {
                api('like', { postId, userId: currentUser.id });
                setPosts(posts.map(p => {
                    if (p.id !== postId) return p;
                    const liked = p.likes.includes(currentUser.id);
                    return { ...p, likes: liked ? p.likes.filter(l => l !== currentUser.id) : [...p.likes, currentUser.id] };
                }));
            };
 
            const handleComment = (postId, text) => {
                api('comment', { postId, userId: currentUser.id, text });
                setPosts(posts.map(p => {
                    if (p.id !== postId) return p;
                    return { ...p, comments: [...p.comments, { id: Date.now(), userId: currentUser.id, text, time: "just now" }] };
                }));
            };
 
            const handlePost = (text, image) => {
                api('post', { userId: currentUser.id, text, image });
                const newPost = { id: Date.now(), userId: currentUser.id, text, image, likes: [], comments: [], shares: 0, time: "just now" };
                setPosts([newPost, ...posts]);
                showNotif("Post published! ✓");
            };
 
            const handleDeletePost = (postId) => {
                api('delete_post', { postId, userId: currentUser.id });
                setPosts(posts.filter(p => p.id !== postId));
                showNotif("Post deleted");
            };
 
            const handleSendFriendRequest = (toId) => {
                if (friendRequests.find(r => r.from === currentUser.id && r.to === toId)) return;
                if (currentUser.friends.includes(toId)) return;
                api('friend_request', { from: currentUser.id, to: toId });
                setFriendRequests([...friendRequests, { from: currentUser.id, to: toId }]);
                showNotif("Friend request sent!");
            };
 
            const handleAcceptRequest = (fromId) => {
                api('accept_request', { from: fromId, to: currentUser.id });
                setFriendRequests(friendRequests.filter(r => !(r.from === fromId && r.to === currentUser.id)));
                const updatedUser = { ...currentUser, friends: [...currentUser.friends, fromId] };
                setCurrentUser(updatedUser);
                setUsers(users.map(u => {
                    if (u.id === currentUser.id) return updatedUser;
                    if (u.id === fromId) return { ...u, friends: [...u.friends, currentUser.id] };
                    return u;
                }));
                showNotif("Friend request accepted! 🎉");
            };
 
            const handleDeclineRequest = (fromId) => {
                setFriendRequests(friendRequests.filter(r => !(r.from === fromId && r.to === currentUser.id)));
                showNotif("Request declined");
            };
 
            const handleSendMessage = (toId, text) => {
                const key = msgKey(currentUser.id, toId);
                const newMsg = { from: currentUser.id, text, time: "just now" };
                api('send_message', { from: currentUser.id, to: toId, text });
                setMessages(m => ({ ...m, [key]: [...(m[key] || []), newMsg] }));
            };
 
            const handleUpdateProfile = async (updates) => {
                await api('update_profile', { ...updates, id: currentUser.id });
                const updated = { ...currentUser, ...updates };
                setCurrentUser(updated);
                setUsers(users.map(u => u.id === currentUser.id ? updated : u));
                showNotif("Profile updated! ✓");
            };
 
            const handleLogout = async () => {
                await api('logout');
                setCurrentUser(null);
                setView("login");
                setAppView('feed');
                setProfileView(null);
                await loadData();
                showNotif('Logged out successfully');
            };
 
            const feedPosts = posts
                .filter(p => p.userId === currentUser.id || currentUser.friends.includes(p.userId))
                .sort((a, b) => b.id - a.id);
 
            const viewingProfile = profileView ? users.find(u => u.id === profileView) : null;
 
            return (
                <div style={{ background: "#0f1419", minHeight: "100vh", color: "#e7e9ea", fontFamily: "'DM Sans', sans-serif" }}>
                    {notification && (
                        <div style={{
                            position: "fixed", top: 20, right: 20, zIndex: 9999,
                            background: notification.type === "success" ? "#22c55e" : "#ef4444",
                            color: "#fff", padding: "12px 20px", borderRadius: 12,
                            fontWeight: 600, fontSize: 14, boxShadow: "0 8px 32px rgba(0,0,0,0.4)",
                            animation: "slideIn 0.3s ease"
                        }}>
                            {notification.msg}
                        </div>
                    )}
                    {/* Top Navbar */}
                    <nav style={{
                        position: "fixed", top: 0, left: 0, right: 0, zIndex: 100,
                        background: "rgba(15,20,25,0.95)", backdropFilter: "blur(20px)",
                        borderBottom: "1px solid rgba(255,255,255,0.08)",
                        display: "flex", alignItems: "center", justifyContent: "space-between",
                        padding: "0 24px", height: 60
                    }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <div style={{ width: 36, height: 36, borderRadius: "50%", background: "#1877f2", display: "flex", alignItems: "center", justifyContent: "center" }}>
                                <span style={{ color: "#fff", fontSize: 22, fontWeight: 900, fontFamily: "Georgia, serif", lineHeight: 1, marginTop: 2 }}>f</span>
                            </div>
                            <span style={{ fontFamily: "'Syne', sans-serif", fontSize: 24, fontWeight: 800, color: "#1877f2", letterSpacing: -0.5 }}>facebook</span>
                        </div>
                        <div style={{ display: "flex", gap: 4 }}>
                            {[
                                { key: "feed", label: "🏠", title: "Feed" },
                                { key: "friends", label: "👥", title: "Friends" + (pendingRequests.length ? ` (${pendingRequests.length})` : "") },
                                { key: "messages", label: "💬", title: "Messages" },
                                { key: "search", label: "🔍", title: "Search" },
                            ].map(item => (
                                <button key={item.key} onClick={() => { setAppView(item.key); setProfileView(null); }}
                                    className="hover-bg"
                                    title={item.title}
                                    style={{
                                        background: appView === item.key ? "rgba(29,155,240,0.15)" : "transparent",
                                        border: "none", color: appView === item.key ? "#1877f2" : "#9ca3af",
                                        padding: "8px 16px", borderRadius: 10, cursor: "pointer",
                                        fontSize: 18, transition: "all 0.2s", position: "relative"
                                    }}>
                                    {item.label}
                                    {item.key === "friends" && pendingRequests.length > 0 && (
                                        <span style={{
                                            position: "absolute", top: 4, right: 6,
                                            background: "#ef4444", color: "#fff", borderRadius: "50%",
                                            width: 16, height: 16, fontSize: 10, fontWeight: 700,
                                            display: "flex", alignItems: "center", justifyContent: "center"
                                        }}>{pendingRequests.length}</span>
                                    )}
                                </button>
                            ))}
                        </div>
                        <button onClick={() => { setAppView("profile"); setProfileView(currentUser.id); }}
                            style={{ background: "none", border: "none", cursor: "pointer" }}>
                            <Avatar user={currentUser} size={36} online />
                        </button>
                    </nav>
                    {/* Main Layout */}
                    <div style={{ display: "flex", maxWidth: 1200, margin: "0 auto", paddingTop: 60, minHeight: "100vh" }}>
                        {/* Left Sidebar */}
                        <aside style={{ width: 240, flexShrink: 0, padding: "24px 16px", position: "sticky", top: 60, height: "calc(100vh - 60px)", overflowY: "auto" }}>
                            <button onClick={() => { setAppView("profile"); setProfileView(currentUser.id); }}
                                className="hover-bg"
                                style={{ display: "flex", alignItems: "center", gap: 12, width: "100%", background: "none", border: "none", color: "#e7e9ea", padding: "10px 12px", borderRadius: 12, cursor: "pointer", marginBottom: 8 }}>
                                <Avatar user={currentUser} size={44} />
                                <div style={{ textAlign: "left" }}>
                                    <div style={{ fontWeight: 700, fontSize: 15 }}>{currentUser.name}</div>
                                    <div style={{ fontSize: 12, color: "#6b7280" }}>View profile</div>
                                </div>
                            </button>
                            <div style={{ borderTop: "1px solid rgba(255,255,255,0.06)", marginTop: 12, paddingTop: 12 }}>
                                <div style={{ fontSize: 11, fontWeight: 700, color: "#6b7280", letterSpacing: 1, marginBottom: 8, paddingLeft: 12 }}>FRIENDS</div>
                                {myFriends.slice(0, 6).map(f => (
                                    <button key={f.id} onClick={() => { setProfileView(f.id); setAppView("profile"); }}
                                        className="hover-bg"
                                        style={{ display: "flex", alignItems: "center", gap: 10, width: "100%", background: "none", border: "none", color: "#e7e9ea", padding: "8px 12px", borderRadius: 10, cursor: "pointer" }}>
                                        <Avatar user={f} size={32} online={f.id % 2 === 0} />
                                        <span style={{ fontSize: 14, fontWeight: 500 }}>{f.name.split(" ")[0]}</span>
                                    </button>
                                ))}
                            </div>
                            <button onClick={handleLogout}
                                style={{ marginTop: 24, width: "100%", padding: "10px", borderRadius: 10, background: "rgba(239,68,68,0.1)", border: "1px solid rgba(239,68,68,0.2)", color: "#f87171", cursor: "pointer", fontSize: 13, fontWeight: 600 }}>
                                Sign Out
                            </button>
                        </aside>
                        {/* Center Content */}
                        <main style={{ flex: 1, padding: "24px 16px", maxWidth: 680, borderLeft: "1px solid rgba(255,255,255,0.06)", borderRight: "1px solid rgba(255,255,255,0.06)" }}>
                            {appView === "feed" && (
                                <FeedView posts={feedPosts} users={users} currentUser={currentUser}
                                    onLike={handleLike} onComment={handleComment} onPost={handlePost}
                                    onDelete={handleDeletePost} onProfileClick={(id) => { setProfileView(id); setAppView("profile"); }} />
                            )}
                            {appView === "profile" && viewingProfile && (
                                <ProfileView user={viewingProfile} currentUser={currentUser} posts={posts} users={users}
                                    onUpdateProfile={handleUpdateProfile} onSendRequest={handleSendFriendRequest}
                                    onLike={handleLike} onComment={handleComment} onDelete={handleDeletePost}
                                    onMessage={(id) => { setActiveChat(id); setAppView("messages"); }}
                                    friendRequests={friendRequests}
                                    onProfileClick={(id) => { setProfileView(id); }} />
                            )}
                            {appView === "friends" && (
                                <FriendsView users={users} currentUser={currentUser} pendingRequests={pendingRequests}
                                    friendRequests={friendRequests}
                                    onAccept={handleAcceptRequest} onDecline={handleDeclineRequest}
                                    onSendRequest={handleSendFriendRequest}
                                    onProfileClick={(id) => { setProfileView(id); setAppView("profile"); }} />
                            )}
                            {appView === "messages" && (
                                <MessagesView users={users} currentUser={currentUser} messages={messages}
                                    activeChat={activeChat} setActiveChat={setActiveChat}
                                    onSend={handleSendMessage} />
                            )}
                            {appView === "search" && (
                                <SearchView users={users} currentUser={currentUser} friendRequests={friendRequests}
                                    onSendRequest={handleSendFriendRequest}
                                    onProfileClick={(id) => { setProfileView(id); setAppView("profile"); }} />
                            )}
                        </main>
                        {/* Right Sidebar */}
                        <aside style={{ width: 240, flexShrink: 0, padding: "24px 16px", position: "sticky", top: 60, height: "calc(100vh - 60px)", overflowY: "auto" }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: "#6b7280", letterSpacing: 1, marginBottom: 12 }}>PEOPLE YOU MAY KNOW</div>
                            {users.filter(u => u.id !== currentUser.id && !currentUser.friends.includes(u.id)).slice(0, 4).map(u => {
                                const alreadySent = friendRequests.find(r => r.from === currentUser.id && r.to === u.id);
                                return (
                                    <div key={u.id} style={{ marginBottom: 16, padding: 12, background: "rgba(255,255,255,0.03)", borderRadius: 12, border: "1px solid rgba(255,255,255,0.06)" }}>
                                        <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 8 }}>
                                            <button onClick={() => { setProfileView(u.id); setAppView("profile"); }} style={{ background: "none", border: "none", cursor: "pointer" }}>
                                                <Avatar user={u} size={36} />
                                            </button>
                                            <div>
                                                <div style={{ fontWeight: 600, fontSize: 13 }}>{u.name}</div>
                                                <div style={{ fontSize: 11, color: "#6b7280" }}>{u.friends.filter(f => currentUser.friends.includes(f)).length} mutual friends</div>
                                            </div>
                                        </div>
                                        <button onClick={() => handleSendFriendRequest(u.id)} disabled={!!alreadySent}
                                            style={{ width: "100%", padding: "6px", borderRadius: 8, fontSize: 12, fontWeight: 600, background: alreadySent ? "rgba(255,255,255,0.05)" : "rgba(29,155,240,0.15)", color: alreadySent ? "#6b7280" : "#1877f2", border: "1px solid", borderColor: alreadySent ? "transparent" : "rgba(29,155,240,0.3)", cursor: alreadySent ? "default" : "pointer" }}>
                                            {alreadySent ? "Request Sent" : "Add Friend"}
                                        </button>
                                    </div>
                                );
                            })}
                        </aside>
                    </div>
                </div>
            );
        }
 
        // ── Auth Screen ────────────────────────────────────────────────────────────
        function AuthScreen({ users, setUsers, setCurrentUser, setView, view }) {
            const [form, setForm] = useState({ name: "", email: "", password: "", bio: "" });
            const [error, setError] = useState("");
 
            const handleLogin = () => {
                const q = form.email.trim().toLowerCase();
                const user = users.find(u =>
                    u.username.toLowerCase() === q ||
                    u.name.toLowerCase() === q ||
                    u.name.toLowerCase().startsWith(q) ||
                    u.name.toLowerCase().split(" ").some(part => part === q)
                );
                if (!user) { setError("Account not found. Click a demo account below or sign up!"); return; }
                if (user.password && form.password !== user.password) {
                    setError("Incorrect password. Try again!");
                    return;
                }
                setCurrentUser(user);
                setView("app");
            };
 
            const handleSignup = () => {
                if (!form.name || !form.email || !form.password) { setError("Please fill in all required fields"); return; }
                if (form.password.length < 3) { setError("Password must be at least 3 characters"); return; }
                const taken = users.find(u => u.username.toLowerCase() === form.email.trim().toLowerCase());
                if (taken) { setError("Username already taken. Choose another!"); return; }
                const newUser = {
                    id: Date.now(), name: form.name, username: form.email.trim().toLowerCase(),
                    password: form.password,
                    bio: form.bio || "New to Facebook! 👋", avatar: getInitials(form.name),
                    cover: "#1a3a5c", friends: [], mutuals: []
                };
                setUsers([...users, newUser]);
                setCurrentUser(newUser);
                setView("app");
            };
 
            return (
                <div style={{
                    minHeight: "100vh", background: "#0f1419",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontFamily: "'DM Sans', sans-serif"
                }}>
                    <style>{`* { box-sizing: border-box; margin: 0; padding: 0; } input, button { font-family: 'DM Sans', sans-serif; } @keyframes fadeUp { from { opacity:0; transform: translateY(30px); } to { opacity:1; transform: translateY(0); } }`}</style>
                    <div style={{ animation: "fadeUp 0.6s ease both", display: "flex", flexDirection: "column", alignItems: "center", gap: 32, width: "100%", maxWidth: 420, padding: 24 }}>
                        <div style={{ textAlign: "center" }}>
                            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 12, marginBottom: 4 }}>
                                <div style={{ width: 56, height: 56, borderRadius: "50%", background: "#1877f2", display: "flex", alignItems: "center", justifyContent: "center" }}>
                                    <span style={{ color: "#fff", fontSize: 36, fontWeight: 900, fontFamily: "Georgia, serif", lineHeight: 1, marginTop: 3 }}>f</span>
                                </div>
                                <span style={{ fontFamily: "'Syne', sans-serif", fontSize: 44, fontWeight: 800, color: "#1877f2", letterSpacing: -2, lineHeight: 1 }}>facebook</span>
                            </div>
                            <div style={{ color: "#6b7280", marginTop: 8, fontSize: 15 }}>Connect with the people that matter</div>
                        </div>
                        <div style={{ background: "rgba(255,255,255,0.04)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 20, padding: 32, width: "100%", backdropFilter: "blur(20px)" }}>
                            <div style={{ display: "flex", gap: 4, marginBottom: 28, background: "rgba(255,255,255,0.05)", borderRadius: 12, padding: 4 }}>
                                {["login", "signup"].map(v => (
                                    <button key={v} onClick={() => { setView(v); setError(""); }}
                                        style={{ flex: 1, padding: "8px", borderRadius: 8, border: "none", cursor: "pointer", fontWeight: 600, fontSize: 14, transition: "all 0.2s", background: view === v ? "#1877f2" : "transparent", color: view === v ? "#fff" : "#6b7280" }}>
                                        {v === "login" ? "Sign In" : "Sign Up"}
                                    </button>
                                ))}
                            </div>
                            {view === "signup" && (
                                <InputField label="Full Name" value={form.name} onChange={v => setForm({ ...form, name: v })} placeholder="Alex Rivera" />
                            )}
                            <InputField label={view === "login" ? "Username or Name" : "Username"} value={form.email} onChange={v => setForm({ ...form, email: v })} placeholder={view === "login" ? "username or full name" : "choose a username"} />
                            <InputField label="Password" type="password" value={form.password} onChange={v => setForm({ ...form, password: v })} placeholder="••••••••" />
                            {view === "signup" && (
                                <InputField label="Bio (optional)" value={form.bio} onChange={v => setForm({ ...form, bio: v })} placeholder="Tell us about yourself..." />
                            )}
                            {error && <div style={{ color: "#f87171", fontSize: 13, marginBottom: 16, background: "rgba(239,68,68,0.1)", padding: "8px 12px", borderRadius: 8 }}>{error}</div>}
                            <button onClick={view === "login" ? handleLogin : handleSignup}
                                style={{ width: "100%", padding: "12px", borderRadius: 12, background: "#1877f2", color: "#fff", border: "none", fontWeight: 700, fontSize: 15, cursor: "pointer", transition: "all 0.2s" }}
                                onMouseEnter={e => e.target.style.background = "#1464d0"}
                                onMouseLeave={e => e.target.style.background = "#1877f2"}>
                                {view === "login" ? "Sign In →" : "Create Account →"}
                            </button>
                            <div style={{ marginTop: 20, fontSize: 12, color: "#6b7280", borderTop: "1px solid rgba(255,255,255,0.1)", paddingTop: 16 }}>
                                <strong>Demo Accounts:</strong><br/>
                                👤 alexrivera / alex123<br/>
                                👤 muhammad / 123<br/>
                                👤 jordankim / jordan123
                            </div>
                        </div>
                    </div>
                </div>
            );
        }
 
        // ── Feed View ──────────────────────────────────────────────────────────────
        function FeedView({ posts, users, currentUser, onLike, onComment, onPost, onDelete, onProfileClick }) {
            const [newPost, setNewPost] = useState("");
            const [imageFile, setImageFile] = useState(null);
            const [imagePreview, setImagePreview] = useState(null);
            const [showUrlInput, setShowUrlInput] = useState(false);
            const [imageUrl, setImageUrl] = useState("");
            const fileInputRef = useRef(null);
 
            const handleFileSelect = (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size should be less than 5MB');
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    return;
                }
                setImageFile(file);
                const reader = new FileReader();
                reader.onload = (e) => {
                    setImagePreview(e.target.result);
                };
                reader.readAsDataURL(file);
            };
 
            const submit = () => {
                if (!newPost.trim() && !imagePreview && !imageUrl) {
                    alert('Please add text or an image');
                    return;
                }
                onPost(newPost, imagePreview || imageUrl || null);
                setNewPost("");
                setImageFile(null);
                setImagePreview(null);
                setImageUrl("");
                setShowUrlInput(false);
            };
 
            return (
                <div>
                    <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 16, padding: 20, marginBottom: 20 }}>
                        <div style={{ display: "flex", gap: 12, marginBottom: 12 }}>
                            <Avatar user={currentUser} size={44} />
                            <textarea value={newPost} onChange={e => setNewPost(e.target.value)}
                                placeholder="What's on your mind?"
                                style={{ flex: 1, background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 12, padding: "12px 16px", color: "#e7e9ea", fontSize: 15, resize: "none", outline: "none", minHeight: 80, lineHeight: 1.5 }} />
                        </div>
                        {imagePreview && (
                            <div style={{ position: "relative", marginBottom: 12, borderRadius: 12, overflow: "hidden" }}>
                                <img src={imagePreview} alt="Preview" style={{ width: "100%", maxHeight: 300, objectFit: "cover" }} />
                                <button
                                    onClick={() => { setImagePreview(null); setImageFile(null); }}
                                    style={{ position: "absolute", top: 8, right: 8, background: "rgba(0,0,0,0.7)", border: "none", color: "#fff", width: 32, height: 32, borderRadius: "50%", cursor: "pointer", fontSize: 18 }}>✕</button>
                            </div>
                        )}
                        {showUrlInput && (
                            <div style={{ marginBottom: 12 }}>
                                <input
                                    value={imageUrl}
                                    onChange={e => setImageUrl(e.target.value)}
                                    placeholder="🔗 Paste image URL (e.g., https://images.unsplash.com/photo-...)"
                                    style={{ width: "100%", padding: "10px 14px", borderRadius: 10, background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.1)", color: "#e7e9ea", fontSize: 13, outline: "none" }} />
                                <div style={{ fontSize: 11, color: "#6b7280", marginTop: 4 }}>
                                    💡 Tip: Google Images pe right-click → "Copy image address"
                                </div>
                            </div>
                        )}
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                            <div style={{ display: "flex", gap: 8 }}>
                                <button
                                    onClick={() => fileInputRef.current?.click()}
                                    style={{ background: "rgba(29,155,240,0.15)", border: "none", color: "#1877f2", cursor: "pointer", fontSize: 13, fontWeight: 600, padding: "8px 16px", borderRadius: 8, display: "flex", alignItems: "center", gap: 6 }}>
                                    📷 Upload Photo
                                </button>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    style={{ display: "none" }}
                                    onChange={handleFileSelect} />
                                <button
                                    onClick={() => setShowUrlInput(!showUrlInput)}
                                    style={{ background: showUrlInput ? "rgba(29,155,240,0.15)" : "transparent", border: "none", color: "#1877f2", cursor: "pointer", fontSize: 13, fontWeight: 600, padding: "8px 16px", borderRadius: 8 }}>
                                    🔗 URL
                                </button>
                            </div>
                            <button
                                onClick={submit}
                                disabled={!newPost.trim() && !imagePreview && !imageUrl}
                                className="btn-primary"
                                style={{ padding: "10px 24px", borderRadius: 12, fontWeight: 700, fontSize: 14, opacity: (!newPost.trim() && !imagePreview && !imageUrl) ? 0.5 : 1 }}>
                                Post
                            </button>
                        </div>
                    </div>
                    {posts.length === 0 && (
                        <div style={{ textAlign: "center", padding: 60, color: "#6b7280" }}>
                            <div style={{ fontSize: 48, marginBottom: 12 }}>🌐</div>
                            <div style={{ fontSize: 18, fontWeight: 600 }}>Your feed is empty</div>
                            <div style={{ fontSize: 14, marginTop: 4 }}>Add some friends to see their posts!</div>
                        </div>
                    )}
                    {posts.map((post, i) => (
                        <PostCard key={post.id} post={post} users={users} currentUser={currentUser}
                            onLike={onLike} onComment={onComment} onDelete={onDelete} onProfileClick={onProfileClick}
                            style={{ animationDelay: `${i * 0.05}s` }} />
                    ))}
                </div>
            );
        }
 
        // ── Post Card ──────────────────────────────────────────────────────────────
        function PostCard({ post, users, currentUser, onLike, onComment, onDelete, onProfileClick, style }) {
            const [showComments, setShowComments] = useState(false);
            const [commentText, setCommentText] = useState("");
            const [shared, setShared] = useState(false);
            const author = users.find(u => u.id === post.userId);
            if (!author) return null;
            const liked = post.likes.includes(currentUser.id);
            const isOwn = post.userId === currentUser.id;
            const submit = () => {
                if (!commentText.trim()) return;
                onComment(post.id, commentText);
                setCommentText("");
            };
            return (
                <div className="post-card" style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 16, marginBottom: 16, overflow: "hidden", ...style }}>
                    <div style={{ padding: 16 }}>
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 12 }}>
                            <button onClick={() => onProfileClick(author.id)} style={{ display: "flex", alignItems: "center", gap: 10, background: "none", border: "none", cursor: "pointer", color: "#e7e9ea" }}>
                                <Avatar user={author} size={42} />
                                <div style={{ textAlign: "left" }}>
                                    <div style={{ fontWeight: 700, fontSize: 15 }}>{author.name}</div>
                                    <div style={{ fontSize: 12, color: "#6b7280" }}>{post.time}</div>
                                </div>
                            </button>
                            {isOwn && (
                                <button onClick={() => onDelete(post.id)}
                                    style={{ background: "none", border: "none", color: "#6b7280", cursor: "pointer", fontSize: 18, padding: 4, borderRadius: 8 }}
                                    title="Delete post">✕</button>
                            )}
                        </div>
                        {post.text && <p style={{ fontSize: 15, lineHeight: 1.6, color: "#d1d5db", marginBottom: post.image ? 12 : 0 }}>{post.text}</p>}
                    </div>
                    {post.image && (
                        <img src={post.image} alt="" style={{ width: "100%", maxHeight: 400, objectFit: "cover", display: "block" }}
                            onError={e => e.target.style.display = "none"} />
                    )}
                    <div style={{ padding: "8px 16px", borderTop: "1px solid rgba(255,255,255,0.06)" }}>
                        <div style={{ display: "flex", gap: 4, color: "#6b7280", fontSize: 13, marginBottom: 8 }}>
                            {post.likes.length > 0 && <span>👍 {post.likes.length}</span>}
                            {post.comments.length > 0 && <span style={{ marginLeft: "auto" }}>{post.comments.length} comment{post.comments.length !== 1 ? "s" : ""}</span>}
                        </div>
                        <div style={{ display: "flex", gap: 4, borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: 8 }}>
                            {[
                                { icon: liked ? "👍" : "👍", label: liked ? "Liked" : "Like", active: liked, action: () => onLike(post.id) },
                                { icon: "💬", label: `Comment${post.comments.length > 0 ? ` (${post.comments.length})` : ""}`, active: false, action: () => setShowComments(!showComments) },
                                { icon: "🔁", label: shared ? "Shared!" : "Share", active: shared, action: () => setShared(true) },
                            ].map(btn => (
                                <button key={btn.label} onClick={btn.action}
                                    style={{ flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 6, padding: "8px 4px", borderRadius: 10, background: btn.active ? "rgba(29,155,240,0.12)" : "transparent", border: "none", color: btn.active ? "#1877f2" : "#6b7280", cursor: "pointer", fontSize: 13, fontWeight: 600, transition: "all 0.15s" }}
                                    className="hover-bg">
                                    <span>{btn.icon}</span> {btn.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    {showComments && (
                        <div style={{ padding: "0 16px 16px", borderTop: "1px solid rgba(255,255,255,0.06)" }}>
                            {post.comments.map(c => {
                                const cu = users.find(u => u.id === c.userId);
                                return cu ? (
                                    <div key={c.id} style={{ display: "flex", gap: 10, marginTop: 12 }}>
                                        <Avatar user={cu} size={32} />
                                        <div style={{ flex: 1, background: "rgba(255,255,255,0.05)", borderRadius: 12, padding: "8px 12px" }}>
                                            <div style={{ fontWeight: 700, fontSize: 13 }}>{cu.name} <span style={{ fontWeight: 400, color: "#6b7280", fontSize: 11 }}>{c.time}</span></div>
                                            <div style={{ fontSize: 14, color: "#d1d5db", marginTop: 2 }}>{c.text}</div>
                                        </div>
                                    </div>
                                ) : null;
                            })}
                            <div style={{ display: "flex", gap: 10, marginTop: 12 }}>
                                <Avatar user={currentUser} size={32} />
                                <div style={{ flex: 1, display: "flex", gap: 8 }}>
                                    <input value={commentText} onChange={e => setCommentText(e.target.value)}
                                        onKeyDown={e => e.key === "Enter" && submit()}
                                        placeholder="Write a comment..."
                                        style={{ flex: 1, background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 20, padding: "8px 16px", color: "#e7e9ea", fontSize: 13, outline: "none" }} />
                                    <button onClick={submit} style={{ background: "#1877f2", border: "none", color: "#fff", padding: "8px 14px", borderRadius: 20, cursor: "pointer", fontWeight: 600, fontSize: 12 }}>→</button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            );
        }
 
        // ── Profile View ───────────────────────────────────────────────────────────
        function ProfileView({ user, currentUser, posts, users, onUpdateProfile, onSendRequest, onLike, onComment, onDelete, onMessage, friendRequests, onProfileClick }) {
            const [editing, setEditing] = useState(false);
            const [editForm, setEditForm] = useState({ name: user.name, bio: user.bio, photo: user.photo || null });
            const photoInputRef = useRef(null);
            const isOwn = user.id === currentUser.id;
            const isFriend = currentUser.friends.includes(user.id);
            const requestSent = friendRequests.find(r => r.from === currentUser.id && r.to === user.id);
            const userPosts = posts.filter(p => p.userId === user.id);
            const saveProfile = () => {
                onUpdateProfile(editForm);
                setEditing(false);
            };
            const handlePhotoChange = (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size should be less than 5MB');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const photoData = ev.target.result;
                    setEditForm(f => ({ ...f, photo: photoData }));
                };
                reader.readAsDataURL(file);
            };
            return (
                <div>
                    <div style={{ background: user.cover || "#1a3a5c", height: 180, borderRadius: 16, marginBottom: -50, position: "relative" }}>
                        <div style={{ position: "absolute", bottom: -50, left: 24 }}>
                            <div style={{ position: "relative", width: 100, height: 100 }}>
                                {(editForm.photo || user.photo) ? (
                                    <img src={editForm.photo || user.photo} alt="profile"
                                        style={{ width: 100, height: 100, borderRadius: "50%", objectFit: "cover", border: "4px solid #0f1419", display: "block" }} />
                                ) : (
                                    <div style={{ width: 100, height: 100, borderRadius: "50%", background: getColor(user.id), display: "flex", alignItems: "center", justifyContent: "center", fontSize: 36, fontWeight: 800, color: "#fff", border: "4px solid #0f1419", fontFamily: "'DM Sans', sans-serif" }}>
                                        {getInitials(user.name)}
                                    </div>
                                )}
                                {isOwn && editing && (
                                    <>
                                        <button onClick={() => photoInputRef.current?.click()}
                                            style={{ position: "absolute", bottom: 2, right: 2, width: 30, height: 30, borderRadius: "50%", background: "#1877f2", border: "2px solid #0f1419", color: "#fff", cursor: "pointer", fontSize: 14, display: "flex", alignItems: "center", justifyContent: "center", zIndex: 10 }}
                                            title="Change photo">📷</button>
                                        <input ref={photoInputRef} type="file" accept="image/*" style={{ display: "none" }}
                                            onChange={handlePhotoChange} />
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                    <div style={{ padding: "60px 24px 24px", background: "rgba(255,255,255,0.02)", borderRadius: 16, border: "1px solid rgba(255,255,255,0.06)", marginBottom: 20 }}>
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                            <div>
                                {editing ? (
                                    <>
                                        <input value={editForm.name} onChange={e => setEditForm({ ...editForm, name: e.target.value })}
                                            style={{ background: "rgba(255,255,255,0.08)", border: "1px solid rgba(255,255,255,0.2)", borderRadius: 8, padding: "6px 12px", color: "#e7e9ea", fontSize: 22, fontWeight: 700, marginBottom: 8, display: "block", width: 280 }} />
                                        <textarea value={editForm.bio} onChange={e => setEditForm({ ...editForm, bio: e.target.value })}
                                            style={{ background: "rgba(255,255,255,0.08)", border: "1px solid rgba(255,255,255,0.2)", borderRadius: 8, padding: "6px 12px", color: "#9ca3af", fontSize: 14, resize: "none", width: 280 }} rows={2} />
                                    </>
                                ) : (
                                    <>
                                        <div style={{ fontFamily: "'Syne', sans-serif", fontSize: 26, fontWeight: 800 }}>{user.name}</div>
                                        <div style={{ color: "#6b7280", fontSize: 14, marginTop: 4 }}>{user.bio}</div>
                                    </>
                                )}
                                <div style={{ display: "flex", gap: 20, marginTop: 12, fontSize: 14 }}>
                                    <span><strong>{userPosts.length}</strong> <span style={{ color: "#6b7280" }}>Posts</span></span>
                                    <span><strong>{user.friends.length}</strong> <span style={{ color: "#6b7280" }}>Friends</span></span>
                                </div>
                            </div>
                            <div style={{ display: "flex", gap: 8 }}>
                                {isOwn && !editing && (
                                    <button onClick={() => setEditing(true)} className="btn-ghost" style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600 }}>Edit Profile</button>
                                )}
                                {isOwn && editing && (
                                    <>
                                        <button onClick={saveProfile} className="btn-primary" style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600 }}>Save</button>
                                        <button onClick={() => { setEditing(false); setEditForm({ name: user.name, bio: user.bio, photo: user.photo || null }); }} className="btn-ghost" style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600 }}>Cancel</button>
                                    </>
                                )}
                                {!isOwn && !isFriend && (
                                    <button onClick={() => onSendRequest(user.id)} disabled={!!requestSent}
                                        style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600, background: requestSent ? "rgba(255,255,255,0.05)" : "#1877f2", color: requestSent ? "#6b7280" : "#fff", border: "none", cursor: requestSent ? "default" : "pointer" }}>
                                        {requestSent ? "Request Sent" : "Add Friend"}
                                    </button>
                                )}
                                {!isOwn && isFriend && (
                                    <button onClick={() => onMessage(user.id)} className="btn-primary" style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600 }}>Message</button>
                                )}
                            </div>
                        </div>
                    </div>
                    <div style={{ fontSize: 13, fontWeight: 700, color: "#6b7280", letterSpacing: 0.5, marginBottom: 12 }}>{isOwn ? "YOUR POSTS" : `${user.name.split(" ")[0].toUpperCase()}'S POSTS`}</div>
                    {userPosts.length === 0 && (
                        <div style={{ textAlign: "center", padding: 40, color: "#6b7280", background: "rgba(255,255,255,0.02)", borderRadius: 12 }}>
                            No posts yet
                        </div>
                    )}
                    {userPosts.map(post => (
                        <PostCard key={post.id} post={post} users={users} currentUser={currentUser}
                            onLike={onLike} onComment={onComment} onDelete={onDelete} onProfileClick={onProfileClick} />
                    ))}
                </div>
            );
        }
 
        // ── Friends View ───────────────────────────────────────────────────────────
        function FriendsView({ users, currentUser, pendingRequests, friendRequests, onAccept, onDecline, onSendRequest, onProfileClick }) {
            const myFriends = users.filter(u => currentUser.friends.includes(u.id));
            const suggested = users.filter(u => u.id !== currentUser.id && !currentUser.friends.includes(u.id));
            return (
                <div>
                    {pendingRequests.length > 0 && (
                        <>
                            <div style={{ fontSize: 13, fontWeight: 700, color: "#6b7280", letterSpacing: 0.5, marginBottom: 12 }}>FRIEND REQUESTS ({pendingRequests.length})</div>
                            {pendingRequests.map(req => {
                                const requester = users.find(u => u.id === req.from);
                                return requester ? (
                                    <div key={req.from} style={{ display: "flex", alignItems: "center", gap: 12, padding: 16, background: "rgba(29,155,240,0.05)", border: "1px solid rgba(29,155,240,0.2)", borderRadius: 14, marginBottom: 10 }}>
                                        <button onClick={() => onProfileClick(requester.id)} style={{ background: "none", border: "none", cursor: "pointer" }}>
                                            <Avatar user={requester} size={48} />
                                        </button>
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontWeight: 700, fontSize: 15 }}>{requester.name}</div>
                                            <div style={{ fontSize: 12, color: "#6b7280" }}>{requester.bio}</div>
                                        </div>
                                        <div style={{ display: "flex", gap: 8 }}>
                                            <button onClick={() => onAccept(req.from)} className="btn-primary" style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600 }}>Accept</button>
                                            <button onClick={() => onDecline(req.from)} className="btn-ghost" style={{ padding: "8px 16px", borderRadius: 10, fontSize: 13, fontWeight: 600 }}>Decline</button>
                                        </div>
                                    </div>
                                ) : null;
                            })}
                            <div style={{ height: 24 }} />
                        </>
                    )}
                    <div style={{ fontSize: 13, fontWeight: 700, color: "#6b7280", letterSpacing: 0.5, marginBottom: 12 }}>YOUR FRIENDS ({myFriends.length})</div>
                    {myFriends.length === 0 ? (
                        <div style={{ textAlign: "center", padding: 40, color: "#6b7280", background: "rgba(255,255,255,0.02)", borderRadius: 12, marginBottom: 24 }}>No friends yet. Send some requests!</div>
                    ) : (
                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 24 }}>
                            {myFriends.map(f => (
                                <div key={f.id} style={{ padding: 16, background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.07)", borderRadius: 14, display: "flex", gap: 12, alignItems: "center" }}>
                                    <button onClick={() => onProfileClick(f.id)} style={{ background: "none", border: "none", cursor: "pointer" }}>
                                        <Avatar user={f} size={48} online={f.id % 2 === 0} />
                                    </button>
                                    <div>
                                        <div style={{ fontWeight: 700, fontSize: 14 }}>{f.name}</div>
                                        <div style={{ fontSize: 12, color: "#6b7280", marginTop: 2 }}>{f.bio?.slice(0, 30)}...</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    <div style={{ fontSize: 13, fontWeight: 700, color: "#6b7280", letterSpacing: 0.5, marginBottom: 12 }}>PEOPLE YOU MAY KNOW</div>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                        {suggested.map(u => {
                            const sent = friendRequests.find(r => r.from === currentUser.id && r.to === u.id);
                            return (
                                <div key={u.id} style={{ padding: 16, background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.07)", borderRadius: 14, textAlign: "center" }}>
                                    <button onClick={() => onProfileClick(u.id)} style={{ background: "none", border: "none", cursor: "pointer", display: "flex", justifyContent: "center", marginBottom: 10 }}>
                                        <Avatar user={u} size={56} />
                                    </button>
                                    <div style={{ fontWeight: 700, fontSize: 14 }}>{u.name}</div>
                                    <div style={{ fontSize: 12, color: "#6b7280", margin: "4px 0 12px" }}>{u.friends.filter(f => currentUser.friends.includes(f)).length} mutual friends</div>
                                    <button onClick={() => onSendRequest(u.id)} disabled={!!sent}
                                        style={{ width: "100%", padding: "8px", borderRadius: 10, fontWeight: 600, fontSize: 13, background: sent ? "rgba(255,255,255,0.05)" : "rgba(29,155,240,0.15)", color: sent ? "#6b7280" : "#1877f2", border: sent ? "none" : "1px solid rgba(29,155,240,0.3)", cursor: sent ? "default" : "pointer" }}>
                                        {sent ? "Sent ✓" : "Add Friend"}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>
            );
        }
 
        // ── Messages View ──────────────────────────────────────────────────────────
        function MessagesView({ users, currentUser, messages, activeChat, setActiveChat, onSend }) {
            const [text, setText] = useState("");
            const bottomRef = useRef(null);
            const myFriends = users.filter(u => currentUser.friends.includes(u.id));
            const chatUser = activeChat ? users.find(u => u.id === activeChat) : null;
            const key = activeChat ? msgKey(currentUser.id, activeChat) : null;
            const chatMsgs = key ? (messages[key] || []) : [];
            useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: "smooth" }); }, [chatMsgs.length]);
            const send = () => {
                if (!text.trim()) return;
                onSend(activeChat, text);
                setText("");
            };
            return (
                <div style={{ display: "flex", gap: 0, height: "calc(100vh - 110px)", overflow: "hidden", borderRadius: 16, border: "1px solid rgba(255,255,255,0.08)" }}>
                    <div style={{ width: 220, flexShrink: 0, borderRight: "1px solid rgba(255,255,255,0.08)", overflowY: "auto", background: "rgba(255,255,255,0.02)" }}>
                        <div style={{ padding: "16px 16px 8px", fontSize: 13, fontWeight: 700, color: "#6b7280", letterSpacing: 0.5 }}>MESSAGES</div>
                        {myFriends.length === 0 && (
                            <div style={{ padding: 20, fontSize: 13, color: "#6b7280", textAlign: "center" }}>Add friends to chat!</div>
                        )}
                        {myFriends.map(f => {
                            const k = msgKey(currentUser.id, f.id);
                            const msgs = messages[k] || [];
                            const last = msgs[msgs.length - 1];
                            return (
                                <button key={f.id} onClick={() => setActiveChat(f.id)}
                                    className="hover-bg"
                                    style={{ display: "flex", alignItems: "center", gap: 10, width: "100%", padding: "10px 16px", background: activeChat === f.id ? "rgba(29,155,240,0.1)" : "transparent", border: "none", color: "#e7e9ea", cursor: "pointer", borderLeft: activeChat === f.id ? "2px solid #1877f2" : "2px solid transparent" }}>
                                    <Avatar user={f} size={36} online={f.id % 2 === 0} />
                                    <div style={{ textAlign: "left", overflow: "hidden" }}>
                                        <div style={{ fontWeight: 600, fontSize: 13 }}>{f.name.split(" ")[0]}</div>
                                        {last && <div style={{ fontSize: 11, color: "#6b7280", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", maxWidth: 120 }}>{last.text}</div>}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                    {chatUser ? (
                        <div style={{ flex: 1, display: "flex", flexDirection: "column" }}>
                            <div style={{ padding: "14px 20px", borderBottom: "1px solid rgba(255,255,255,0.08)", display: "flex", alignItems: "center", gap: 12 }}>
                                <Avatar user={chatUser} size={40} online={chatUser.id % 2 === 0} />
                                <div>
                                    <div style={{ fontWeight: 700, fontSize: 15 }}>{chatUser.name}</div>
                                    <div style={{ fontSize: 12, color: chatUser.id % 2 === 0 ? "#22c55e" : "#6b7280" }}>{chatUser.id % 2 === 0 ? "Active now" : "Last seen recently"}</div>
                                </div>
                            </div>
                            <div style={{ flex: 1, overflowY: "auto", padding: 20, display: "flex", flexDirection: "column", gap: 10 }}>
                                {chatMsgs.length === 0 && (
                                    <div style={{ textAlign: "center", color: "#6b7280", marginTop: 40 }}>
                                        <div style={{ fontSize: 32 }}>👋</div>
                                        <div style={{ marginTop: 8 }}>Start a conversation with {chatUser.name.split(" ")[0]}!</div>
                                    </div>
                                )}
                                {chatMsgs.map((msg, i) => {
                                    const isMine = msg.from === currentUser.id;
                                    return (
                                        <div key={i} style={{ display: "flex", justifyContent: isMine ? "flex-end" : "flex-start", gap: 8, alignItems: "flex-end" }}>
                                            {!isMine && <Avatar user={chatUser} size={28} />}
                                            <div style={{ maxWidth: "70%" }}>
                                                <div style={{ background: isMine ? "#1877f2" : "rgba(255,255,255,0.08)", borderRadius: isMine ? "18px 18px 4px 18px" : "18px 18px 18px 4px", padding: "10px 14px", color: "#fff", fontSize: 14, lineHeight: 1.4 }}>
                                                    {msg.text}
                                                </div>
                                                <div style={{ fontSize: 11, color: "#6b7280", marginTop: 3, textAlign: isMine ? "right" : "left" }}>{msg.time}</div>
                                            </div>
                                        </div>
                                    );
                                })}
                                <div ref={bottomRef} />
                            </div>
                            <div style={{ padding: 16, borderTop: "1px solid rgba(255,255,255,0.08)", display: "flex", gap: 10 }}>
                                <input value={text} onChange={e => setText(e.target.value)} onKeyDown={e => e.key === "Enter" && send()}
                                    placeholder={`Message ${chatUser.name.split(" ")[0]}...`}
                                    style={{ flex: 1, background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 24, padding: "12px 18px", color: "#e7e9ea", fontSize: 14, outline: "none" }} />
                                <button onClick={send} style={{ background: "#1877f2", border: "none", color: "#fff", width: 44, height: 44, borderRadius: "50%", cursor: "pointer", fontSize: 18, display: "flex", alignItems: "center", justifyContent: "center" }}>→</button>
                            </div>
                        </div>
                    ) : (
                        <div style={{ flex: 1, display: "flex", alignItems: "center", justifyContent: "center", flexDirection: "column", gap: 12, color: "#6b7280" }}>
                            <div style={{ fontSize: 48 }}>💬</div>
                            <div style={{ fontSize: 18, fontWeight: 600 }}>Your Messages</div>
                            <div style={{ fontSize: 14 }}>Select a conversation to start chatting</div>
                        </div>
                    )}
                </div>
            );
        }
 
        // ── Search View ────────────────────────────────────────────────────────────
        function SearchView({ users, currentUser, friendRequests, onSendRequest, onProfileClick }) {
            const [query, setQuery] = useState("");
            const results = query.trim().length > 0
                ? users.filter(u => u.id !== currentUser.id && (u.name.toLowerCase().includes(query.toLowerCase()) || u.username.toLowerCase().includes(query.toLowerCase())))
                : users.filter(u => u.id !== currentUser.id);
            return (
                <div>
                    <div style={{ marginBottom: 20 }}>
                        <input value={query} onChange={e => setQuery(e.target.value)}
                            placeholder="🔍  Search for people..."
                            style={{ width: "100%", padding: "14px 20px", borderRadius: 14, background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.1)", color: "#e7e9ea", fontSize: 16, outline: "none" }} />
                    </div>
                    <div style={{ fontSize: 13, fontWeight: 700, color: "#6b7280", letterSpacing: 0.5, marginBottom: 12 }}>
                        {query ? `RESULTS FOR "${query.toUpperCase()}"` : "ALL USERS"}
                    </div>
                    {results.length === 0 && (
                        <div style={{ textAlign: "center", padding: 40, color: "#6b7280" }}>No users found</div>
                    )}
                    {results.map(u => {
                        const isFriend = currentUser.friends.includes(u.id);
                        const sent = friendRequests.find(r => r.from === currentUser.id && r.to === u.id);
                        const mutuals = u.friends.filter(f => currentUser.friends.includes(f)).length;
                        return (
                            <div key={u.id} style={{ display: "flex", alignItems: "center", gap: 14, padding: 16, background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.07)", borderRadius: 14, marginBottom: 10 }}>
                                <button onClick={() => onProfileClick(u.id)} style={{ background: "none", border: "none", cursor: "pointer" }}>
                                    <Avatar user={u} size={52} online={u.id % 2 === 0} />
                                </button>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontWeight: 700, fontSize: 15 }}>{u.name}</div>
                                    <div style={{ fontSize: 12, color: "#6b7280" }}>@{u.username}</div>
                                    <div style={{ fontSize: 12, color: "#9ca3af", marginTop: 4 }}>{u.bio}</div>
                                    {mutuals > 0 && <div style={{ fontSize: 11, color: "#1877f2", marginTop: 4 }}>👥 {mutuals} mutual friend{mutuals !== 1 ? "s" : ""}</div>}
                                </div>
                                {isFriend ? (
                                    <span style={{ fontSize: 12, color: "#22c55e", fontWeight: 600, background: "rgba(34,197,94,0.1)", padding: "6px 14px", borderRadius: 20 }}>Friends ✓</span>
                                ) : (
                                    <button onClick={() => onSendRequest(u.id)} disabled={!!sent}
                                        style={{ padding: "8px 18px", borderRadius: 20, fontWeight: 600, fontSize: 13, background: sent ? "rgba(255,255,255,0.05)" : "#1877f2", color: sent ? "#6b7280" : "#fff", border: "none", cursor: sent ? "default" : "pointer" }}>
                                        {sent ? "Sent ✓" : "Add Friend"}
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            );
        }
 
        ReactDOM.createRoot(document.getElementById('root')).render(<App />);
    </script>
</body>
</html>
