package tw.artick.doujinhelper;

import android.app.Activity;
import android.content.pm.ApplicationInfo;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.graphics.Color;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Bundle;
import android.view.Gravity;
import android.widget.Button;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import java.util.Arrays;

/** Native first-run shell. Circle.ms's final OAuth endpoint is wired after API approval. */
public class MainActivity extends Activity {
    private static final String PREFS = "doujin_helper_auth";
    private static final String AUTH_URI = "https://webcatalog.circle.ms/";
    private static final int TEAL = Color.rgb(31, 76, 78);
    private static final int TEAL_LIGHT = Color.rgb(43, 126, 132);
    private static final int INK = Color.rgb(29, 43, 46);
    private static final int PAPER = Color.rgb(247, 249, 247);
    private static final int LINE = Color.rgb(208, 220, 218);

    private SharedPreferences auth;
    private CatalogDb db;
    private LinearLayout root;
    private String activeTab = "map";
    private boolean previewMode;

    @Override protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        auth = getSharedPreferences(PREFS, MODE_PRIVATE);
        db = new CatalogDb(this);
        previewMode = auth.getBoolean("preview", false);
        render();
        handleIntent(getIntent());
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleIntent(intent);
    }

    private void handleIntent(Intent intent) {
        Uri uri = intent == null ? null : intent.getData();
        if (uri == null || !"tw.artick.doujinhelper".equals(uri.getScheme())) return;
        if ("success".equals(uri.getQueryParameter("status"))) {
            auth.edit().putBoolean("authorized", true).putBoolean("preview", false).apply();
            previewMode = false;
            render();
        } else toast("Circle.ms 授權尚未完成，請重新嘗試。");
    }

    private boolean isSignedIn() { return auth.getBoolean("authorized", false) || previewMode; }

    private void render() {
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(PAPER);
        if (isSignedIn()) renderApp(); else renderLogin();
        setContentView(root);
    }

    private void renderLogin() {
        LinearLayout content = column(24);
        content.setGravity(Gravity.CENTER_HORIZONTAL);
        content.addView(label("Personal Doujin Helper", 14, TEAL_LIGHT), wrap());
        TextView title = label("你的同人誌活動工具", 28, INK);
        title.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        content.addView(title, margin(0, 10, 0, 8));
        TextView copy = label("登入 Circle.ms 後，將目錄資料保存在這台手機，離線查看地圖、喜愛社團與活動資訊。", 16, Color.DKGRAY);
        copy.setGravity(Gravity.CENTER);
        content.addView(copy, margin(0, 0, 0, 28));

        LinearLayout card = card();
        TextView status = label("尚未登入", 20, INK);
        status.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        card.addView(status, wrap());
        card.addView(label("正式版會在這裡開啟 Circle.ms 官方登入與 App 授權。完成授權後，APK 才會進入目錄功能。", 15, Color.DKGRAY), margin(0, 10, 0, 18));
        Button login = actionButton("前往 Circle.ms 登入與授權", TEAL_LIGHT);
        login.setOnClickListener(v -> openAuth());
        card.addView(login, fullWidth());
        card.addView(label("目前 C109 授權區間尚未開放，因此授權回傳可能會失敗。", 13, Color.rgb(110, 80, 40)), margin(0, 14, 0, 0));
        content.addView(card, fullWidthWithMargins(0, 12, 0, 0));

        if (isDebugBuild()) {
            Button preview = actionButton("開發預覽：使用 C108 本機資料", Color.rgb(100, 112, 112));
            preview.setOnClickListener(v -> {
                previewMode = true;
                auth.edit().putBoolean("preview", true).apply();
                db.seedDemoData();
                render();
            });
            content.addView(preview, fullWidthWithMargins(0, 18, 0, 0));
        }
        root.addView(content, new LinearLayout.LayoutParams(-1, 0, 1));
    }

    private void renderApp() {
        LinearLayout header = column(18);
        header.addView(label(previewMode ? "C108 開發預覽" : "Personal Doujin Helper", 13, TEAL_LIGHT), wrap());
        TextView title = label(tabTitle(), 27, INK);
        title.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        header.addView(title, margin(0, 4, 0, 0));
        root.addView(header, fullWidthWithMargins(20, 18, 20, 8));

        ScrollView body = new ScrollView(this);
        LinearLayout page = column(18);
        if ("map".equals(activeTab)) renderMap(page);
        if ("favorites".equals(activeTab)) renderFavorites(page);
        if ("circles".equals(activeTab)) renderCircles(page);
        if ("settings".equals(activeTab)) renderSettings(page);
        body.addView(page);
        root.addView(body, new LinearLayout.LayoutParams(-1, 0, 1));
        renderTabs();
    }

    private String tabTitle() {
        if ("favorites".equals(activeTab)) return "喜愛清單";
        if ("circles".equals(activeTab)) return "所有社團";
        if ("settings".equals(activeTab)) return "設定";
        return "地圖";
    }

    private void renderMap(LinearLayout page) {
        page.addView(label("先選活動與館別，再開啟離線地圖。未來會由 Circle.ms 授權後下載資料。", 15, Color.DKGRAY), wrap());
        LinearLayout controls = card();
        controls.addView(label("活動", 13, Color.GRAY), wrap());
        controls.addView(label("C108 · 夏コミ", 19, INK), margin(0, 4, 0, 14));
        controls.addView(label("館別", 13, Color.GRAY), wrap());
        HorizontalScrollView halls = new HorizontalScrollView(this);
        LinearLayout hallRow = new LinearLayout(this);
        hallRow.setPadding(0, 8, 0, 4);
        for (String hall : Arrays.asList("東123", "東456", "西12", "南12")) {
            Button button = compactButton(hall, hall.equals("東123") ? TEAL_LIGHT : Color.WHITE, hall.equals("東123") ? Color.WHITE : INK);
            hallRow.addView(button, wrapWithMargins(0, 0, 8, 0));
        }
        halls.addView(hallRow);
        controls.addView(halls, fullWidth());
        page.addView(controls, fullWidth());

        LinearLayout mapCard = card();
        TextView mapTitle = label("1日目 / E123", 20, INK);
        mapTitle.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        mapCard.addView(mapTitle, wrap());
        mapCard.addView(label("地圖預覽將在下載 C108 資料後顯示。", 14, Color.DKGRAY), margin(0, 6, 0, 14));
        TextView mapHint = label("這裡會放置可縮放、旋轉、點選攤位的原生地圖。", 16, TEAL);
        mapHint.setGravity(Gravity.CENTER);
        mapCard.addView(mapHint, fixedHeight(150));
        page.addView(mapCard, fullWidth());
    }

    private void renderFavorites(LinearLayout page) {
        page.addView(label("你的收藏會儲存在手機本機，之後可以與 Circle.ms 收藏同步。", 15, Color.DKGRAY), wrap());
        for (String item : Arrays.asList("身から出た鱧", "Frenchletter", "Personal Doujin Helper")) {
            LinearLayout row = card();
            TextView name = label(item, 18, INK);
            name.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
            row.addView(name, wrap());
            row.addView(label("2日目 · 東 セ32a", 14, Color.DKGRAY), margin(0, 5, 0, 0));
            page.addView(row, fullWidth());
        }
    }

    private void renderCircles(LinearLayout page) {
        page.addView(label("全部社團資料會在目錄下載後存於本機。", 15, Color.DKGRAY), wrap());
        TextView search = label("搜尋社團名稱、作者或攤位", 16, Color.GRAY);
        search.setBackground(round(Color.WHITE, LINE, 1, 12));
        search.setPadding(16, 18, 16, 18);
        page.addView(search, fullWidth());
        for (String item : Arrays.asList("身から出た鱧", "Frenchletter", "R.S.I.", "White-Wind")) {
            LinearLayout row = card();
            row.addView(label(item, 18, INK), wrap());
            row.addView(label("未設定 · 可加入喜愛清單", 14, Color.DKGRAY), margin(0, 5, 0, 0));
            page.addView(row, fullWidth());
        }
    }

    private void renderSettings(LinearLayout page) {
        LinearLayout account = card();
        account.addView(label("Circle.ms 帳號", 13, Color.GRAY), wrap());
        account.addView(label(previewMode ? "C108 開發預覽" : "已授權帳號", 19, INK), margin(0, 5, 0, 16));
        Button authButton = actionButton("重新進行 Circle.ms 授權", TEAL_LIGHT);
        authButton.setOnClickListener(v -> openAuth());
        account.addView(authButton, fullWidth());
        page.addView(account, fullWidth());

        LinearLayout local = card();
        local.addView(label("本機資料", 13, Color.GRAY), wrap());
        local.addView(label("SQLite 離線資料庫", 18, INK), margin(0, 5, 0, 4));
        local.addView(label("目錄、收藏與地圖標記會保存在這台手機。", 14, Color.DKGRAY), wrap());
        page.addView(local, fullWidth());

        Button signOut = actionButton("登出並清除授權狀態", Color.rgb(145, 75, 74));
        signOut.setOnClickListener(v -> { auth.edit().clear().apply(); previewMode = false; render(); });
        page.addView(signOut, fullWidthWithMargins(0, 6, 0, 0));
    }

    private void renderTabs() {
        LinearLayout tabs = new LinearLayout(this);
        tabs.setPadding(12, 10, 12, 16);
        tabs.setGravity(Gravity.CENTER);
        String[][] tabData = {{"map", "地圖"}, {"favorites", "喜愛清單"}, {"circles", "所有社團"}, {"settings", "設定"}};
        for (String[] tab : tabData) {
            Button button = compactButton(tab[1], tab[0].equals(activeTab) ? TEAL : Color.WHITE, tab[0].equals(activeTab) ? Color.WHITE : INK);
            button.setOnClickListener(v -> { activeTab = tab[0]; render(); });
            tabs.addView(button, new LinearLayout.LayoutParams(0, dp(48), 1));
        }
        root.addView(tabs, new LinearLayout.LayoutParams(-1, -2));
    }

    private void openAuth() {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(AUTH_URI)));
            toast("已開啟 Circle.ms。完成 App 授權後，請返回本 App。");
        } catch (Exception error) { toast("無法開啟 Circle.ms 登入頁。"); }
    }

    private boolean isDebugBuild() {
        return (getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0;
    }

    private void toast(String message) { android.widget.Toast.makeText(this, message, android.widget.Toast.LENGTH_LONG).show(); }

    private TextView label(String text, int size, int color) {
        TextView view = new TextView(this);
        view.setText(text);
        view.setTextSize(size);
        view.setTextColor(color);
        view.setGravity(Gravity.CENTER_VERTICAL);
        return view;
    }

    private Button actionButton(String text, int color) {
        Button button = compactButton(text, color, Color.WHITE);
        button.setMinHeight(dp(50));
        return button;
    }

    private Button compactButton(String text, int background, int foreground) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(foreground);
        button.setTextSize(14);
        button.setAllCaps(false);
        button.setMinHeight(dp(44));
        button.setPadding(dp(12), 0, dp(12), 0);
        button.setBackground(round(background, background == Color.WHITE ? LINE : background, 1, 12));
        return button;
    }

    private LinearLayout column(int padding) {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        layout.setPadding(dp(padding), dp(padding), dp(padding), dp(padding));
        return layout;
    }

    private LinearLayout card() {
        LinearLayout layout = column(18);
        layout.setBackground(round(Color.WHITE, LINE, 1, 16));
        return layout;
    }

    private android.graphics.drawable.GradientDrawable round(int fill, int stroke, int strokeWidth, int radius) {
        android.graphics.drawable.GradientDrawable drawable = new android.graphics.drawable.GradientDrawable();
        drawable.setColor(fill);
        drawable.setStroke(dp(strokeWidth), stroke);
        drawable.setCornerRadius(dp(radius));
        return drawable;
    }

    private LinearLayout.LayoutParams wrap() { return new LinearLayout.LayoutParams(-1, -2); }
    private LinearLayout.LayoutParams fullWidth() { return new LinearLayout.LayoutParams(-1, -2); }
    private LinearLayout.LayoutParams fixedHeight(int height) { return new LinearLayout.LayoutParams(-1, dp(height)); }
    private LinearLayout.LayoutParams fullWidthWithMargins(int l, int t, int r, int b) {
        LinearLayout.LayoutParams params = fullWidth();
        params.setMargins(dp(l), dp(t), dp(r), dp(b));
        return params;
    }
    private LinearLayout.LayoutParams wrapWithMargins(int l, int t, int r, int b) {
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(-2, -2);
        params.setMargins(dp(l), dp(t), dp(r), dp(b));
        return params;
    }
    private LinearLayout.LayoutParams margin(int l, int t, int r, int b) { return fullWidthWithMargins(l, t, r, b); }
    private int dp(int value) { return (int) (value * getResources().getDisplayMetrics().density + 0.5f); }

    private static class CatalogDb extends SQLiteOpenHelper {
        CatalogDb(Context context) { super(context, "personal_doujin_catalog.db", null, 1); }
        @Override public void onCreate(SQLiteDatabase db) {
            db.execSQL("CREATE TABLE profile (id INTEGER PRIMARY KEY, circlems_user_id TEXT, event_id TEXT, authorized INTEGER NOT NULL DEFAULT 0)");
            db.execSQL("CREATE TABLE circles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, location TEXT, is_favorite INTEGER NOT NULL DEFAULT 0, note TEXT)");
            db.execSQL("CREATE TABLE maps (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id TEXT NOT NULL, map_name TEXT NOT NULL, image_path TEXT, payload TEXT)");
        }
        @Override public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) { }
        void seedDemoData() {
            SQLiteDatabase database = getWritableDatabase();
            database.execSQL("DELETE FROM circles");
            database.execSQL("INSERT INTO circles(name, location, is_favorite) VALUES ('身から出た鱧', '2日目 東 セ32a', 1)");
            database.execSQL("INSERT INTO circles(name, location, is_favorite) VALUES ('Frenchletter', '2日目 東 A24a', 1)");
            database.execSQL("INSERT INTO maps(event_id, map_name) VALUES ('230', 'E123')");
        }
    }
}
