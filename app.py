"""演習記録アプリ - メインアプリケーション"""
import os
from datetime import datetime, date

from dotenv import load_dotenv
load_dotenv()

import pymysql
import openpyxl
from flask import Flask, render_template, request, redirect, url_for, jsonify, flash

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "study-record-app-secret-key")

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")

SUBJECTS = ["FAR", "BAR", "REG", "AUD"]

# MySQL接続設定（Xserverの値を環境変数 or 直接設定）
DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "localhost"),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASSWORD", ""),
    "database": os.environ.get("DB_NAME", "study_records"),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
}


def get_excel_path(subject):
    """科目ごとのExcelファイルパスを返す"""
    return os.path.join(DATA_DIR, f"problems_{subject}.xlsx")


def get_db():
    """データベース接続を取得"""
    return pymysql.connect(**DB_CONFIG)


def init_db():
    """データベースを初期化"""
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        CREATE TABLE IF NOT EXISTS sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(10) NOT NULL DEFAULT '',
            chapter_name VARCHAR(255) NOT NULL,
            study_date DATE NOT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    """)
    cur.execute("""
        CREATE TABLE IF NOT EXISTS records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            chapter_name VARCHAR(255) NOT NULL,
            problem_number INT NOT NULL,
            result ENUM('correct', 'incorrect') NOT NULL,
            study_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    """)
    cur.execute("""
        CREATE TABLE IF NOT EXISTS custom_session_problems (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            chapter_name VARCHAR(255) NOT NULL,
            problem_number INT NOT NULL,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    """)
    conn.commit()
    cur.close()
    conn.close()


def load_problems_from_excel(subject):
    """科目のExcelファイルから問題データを読み込む"""
    excel_path = get_excel_path(subject)
    if not os.path.exists(excel_path):
        return {}

    wb = openpyxl.load_workbook(excel_path, read_only=True)
    ws = wb.active

    problems = {}
    for row in ws.iter_rows(min_row=2, values_only=True):
        if row[0] is None or row[1] is None:
            continue
        chapter_name = str(row[0]).strip()
        problem_number = int(row[1])
        if chapter_name not in problems:
            problems[chapter_name] = []
        problems[chapter_name].append(problem_number)

    wb.close()

    for chapter in problems:
        problems[chapter].sort()

    return problems


def get_stats(subject=None, chapter_name=None, max_accuracy=None):
    """問題ごとの統計を取得（完了済みセッションのみ）"""
    conn = get_db()
    cur = conn.cursor()

    base_query = """
        SELECT
            r.chapter_name,
            r.problem_number,
            COUNT(*) as total_attempts,
            SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct_count,
            ROUND(
                100.0 * SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) / COUNT(*),
                1
            ) as accuracy,
            MAX(r.study_date) as last_study_date
        FROM records r
        JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL
    """

    params = []
    if subject:
        base_query += " AND s.subject = %s"
        params.append(subject)
    if chapter_name:
        base_query += " AND r.chapter_name = %s"
        params.append(chapter_name)

    base_query += " GROUP BY r.chapter_name, r.problem_number"
    cur.execute(base_query, params)
    rows = cur.fetchall()
    cur.close()
    conn.close()

    stats = [dict(row) for row in rows]

    if max_accuracy is not None:
        stats = [s for s in stats if float(s["accuracy"]) <= max_accuracy]

    return stats


@app.route("/")
def home():
    """科目選択ホーム"""
    conn = get_db()
    cur = conn.cursor()
    subject_info = []
    for subj in SUBJECTS:
        problems = load_problems_from_excel(subj)
        chapter_count = len(problems)
        total_problems = sum(len(v) for v in problems.values())
        cur.execute(
            "SELECT COUNT(*) as cnt FROM sessions WHERE subject = %s AND finished_at IS NOT NULL",
            (subj,),
        )
        session_count = cur.fetchone()["cnt"]
        cur.execute(
            "SELECT COUNT(*) as cnt FROM sessions WHERE subject = %s AND finished_at IS NULL",
            (subj,),
        )
        active_count = cur.fetchone()["cnt"]
        subject_info.append({
            "name": subj,
            "chapter_count": chapter_count,
            "total_problems": total_problems,
            "session_count": session_count,
            "active_count": active_count,
        })
    cur.close()
    conn.close()
    return render_template("home.html", subjects=subject_info)


@app.route("/<subject>")
def index(subject):
    """チャプター選択ページ（科目別）"""
    if subject not in SUBJECTS:
        flash("無効な科目です。", "error")
        return redirect(url_for("home"))

    problems = load_problems_from_excel(subject)
    chapters = list(problems.keys())

    conn = get_db()
    cur = conn.cursor()
    chapter_info = []
    for ch in chapters:
        problem_count = len(problems[ch])
        cur.execute(
            "SELECT COUNT(*) as cnt FROM sessions WHERE subject = %s AND chapter_name = %s AND finished_at IS NOT NULL",
            (subject, ch),
        )
        session_count = cur.fetchone()["cnt"]
        cur.execute(
            "SELECT id FROM sessions WHERE subject = %s AND chapter_name = %s AND finished_at IS NULL ORDER BY id DESC LIMIT 1",
            (subject, ch),
        )
        active_session = cur.fetchone()
        chapter_info.append({
            "name": ch,
            "problem_count": problem_count,
            "session_count": session_count,
            "active_session_id": active_session["id"] if active_session else None,
        })
    cur.close()
    conn.close()

    return render_template("index.html", subject=subject, chapters=chapter_info)


@app.route("/<subject>/start_session/<path:chapter_name>", methods=["POST"])
def start_session(subject, chapter_name):
    """新しい学習セッションを開始"""
    if subject not in SUBJECTS:
        flash("無効な科目です。", "error")
        return redirect(url_for("home"))

    problems = load_problems_from_excel(subject)
    if chapter_name not in problems:
        flash("指定されたチャプターが見つかりません。", "error")
        return redirect(url_for("index", subject=subject))

    today = date.today().isoformat()
    conn = get_db()
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO sessions (subject, chapter_name, study_date) VALUES (%s, %s, %s)",
        (subject, chapter_name, today),
    )
    session_id = cur.lastrowid
    conn.commit()
    cur.close()
    conn.close()

    return redirect(url_for("study", subject=subject, session_id=session_id))


@app.route("/<subject>/study/<int:session_id>")
def study(subject, session_id):
    """学習ページ"""
    if subject not in SUBJECTS:
        flash("無効な科目です。", "error")
        return redirect(url_for("home"))

    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT * FROM sessions WHERE id = %s", (session_id,))
    session = cur.fetchone()

    if not session:
        flash("セッションが見つかりません。", "error")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    if session["finished_at"]:
        flash("このセッションは既に終了しています。", "info")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    chapter_name = session["chapter_name"]
    problems = load_problems_from_excel(subject)
    if chapter_name not in problems:
        flash("チャプターのデータが見つかりません。", "error")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    problem_numbers = problems[chapter_name]

    session_records = {}
    cur.execute(
        "SELECT problem_number, result FROM records WHERE session_id = %s",
        (session_id,)
    )
    for row in cur.fetchall():
        session_records[row["problem_number"]] = row["result"]

    problem_stats = {}
    for pn in problem_numbers:
        cur.execute("""
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct
            FROM records r
            JOIN sessions s ON r.session_id = s.id
            WHERE s.finished_at IS NOT NULL AND r.chapter_name = %s AND r.problem_number = %s
        """, (chapter_name, pn))
        row = cur.fetchone()

        total = row["total"]
        correct = row["correct"] or 0
        accuracy = round(100.0 * correct / total, 1) if total > 0 else None

        problem_stats[pn] = {
            "total": total,
            "correct": correct,
            "accuracy": accuracy,
            "session_result": session_records.get(pn),
        }
    cur.close()
    conn.close()

    answered = sum(1 for r in session_records.values() if r)
    total_problems = len(problem_numbers)

    return render_template(
        "study.html",
        subject=subject,
        session_id=session_id,
        chapter_name=chapter_name,
        problem_numbers=problem_numbers,
        problem_stats=problem_stats,
        study_date=session["study_date"],
        answered=answered,
        total_problems=total_problems,
    )


@app.route("/record", methods=["POST"])
def record():
    """回答を記録するAPI"""
    data = request.get_json()
    session_id = data.get("session_id")
    chapter_name = data.get("chapter_name")
    problem_number = data.get("problem_number")
    result = data.get("result")

    if result not in ("correct", "incorrect"):
        return jsonify({"error": "無効な結果です"}), 400

    conn = get_db()
    cur = conn.cursor()

    cur.execute(
        "SELECT * FROM sessions WHERE id = %s AND finished_at IS NULL",
        (session_id,)
    )
    session = cur.fetchone()
    if not session:
        cur.close()
        conn.close()
        return jsonify({"error": "セッションが無効です"}), 400

    study_date = session["study_date"]

    cur.execute(
        "DELETE FROM records WHERE session_id = %s AND problem_number = %s AND chapter_name = %s",
        (session_id, problem_number, chapter_name),
    )
    cur.execute(
        "INSERT INTO records (session_id, chapter_name, problem_number, result, study_date) VALUES (%s, %s, %s, %s, %s)",
        (session_id, chapter_name, problem_number, result, study_date),
    )
    conn.commit()

    cur.execute(
        "SELECT COUNT(*) as cnt FROM records WHERE session_id = %s",
        (session_id,)
    )
    answered = cur.fetchone()["cnt"]

    cur.execute("""
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct
        FROM records r
        JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL AND r.chapter_name = %s AND r.problem_number = %s
    """, (chapter_name, problem_number))
    row = cur.fetchone()
    cur.close()
    conn.close()

    total = row["total"]
    correct = row["correct"] or 0
    accuracy = round(100.0 * correct / total, 1) if total > 0 else None

    return jsonify({
        "success": True,
        "total": total,
        "correct": correct,
        "accuracy": accuracy,
        "answered": answered,
    })


@app.route("/undo", methods=["POST"])
def undo():
    """セッション内の回答を取り消すAPI"""
    data = request.get_json()
    session_id = data.get("session_id")
    problem_number = data.get("problem_number")
    chapter_name = data.get("chapter_name")

    conn = get_db()
    cur = conn.cursor()
    cur.execute(
        "DELETE FROM records WHERE session_id = %s AND problem_number = %s AND chapter_name = %s",
        (session_id, problem_number, chapter_name),
    )
    conn.commit()

    cur.execute(
        "SELECT COUNT(*) as cnt FROM records WHERE session_id = %s",
        (session_id,)
    )
    answered = cur.fetchone()["cnt"]

    cur.execute("""
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct
        FROM records r
        JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL AND r.chapter_name = %s AND r.problem_number = %s
    """, (chapter_name, problem_number))
    row = cur.fetchone()
    cur.close()
    conn.close()

    total = row["total"]
    correct = row["correct"] or 0
    accuracy = round(100.0 * correct / total, 1) if total > 0 else None

    return jsonify({
        "success": True,
        "total": total,
        "correct": correct,
        "accuracy": accuracy,
        "answered": answered,
    })


@app.route("/<subject>/finish_session/<int:session_id>", methods=["POST"])
def finish_session(subject, session_id):
    """学習セッションを終了"""
    if subject not in SUBJECTS:
        return redirect(url_for("home"))

    conn = get_db()
    cur = conn.cursor()
    cur.execute(
        "SELECT * FROM sessions WHERE id = %s AND finished_at IS NULL",
        (session_id,)
    )
    session = cur.fetchone()

    if not session:
        flash("セッションが見つかりません。", "error")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    cur.execute(
        "UPDATE sessions SET finished_at = NOW() WHERE id = %s",
        (session_id,),
    )
    conn.commit()

    cur.execute("""
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN result = 'correct' THEN 1 ELSE 0 END) as correct,
            SUM(CASE WHEN result = 'incorrect' THEN 1 ELSE 0 END) as incorrect
        FROM records WHERE session_id = %s
    """, (session_id,))
    summary = cur.fetchone()
    cur.close()
    conn.close()

    total = summary["total"]
    correct = summary["correct"]
    if total > 0:
        acc = round(100.0 * correct / total, 1)
        flash(f"学習終了！ 回答数: {total}問 / 正解: {correct}問 / 正答率: {acc}%", "success")
    else:
        flash("学習終了しました（回答なし）。", "info")

    return redirect(url_for("index", subject=subject))


@app.route("/start_custom_session", methods=["POST"])
def start_custom_session():
    """ダッシュボードから選択した問題でカスタムセッションを開始"""
    data = request.get_json()
    problems = data.get("problems", [])
    subject = data.get("subject", "")

    if not problems:
        return jsonify({"error": "問題が選択されていません"}), 400

    today = date.today().isoformat()
    conn = get_db()
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO sessions (subject, chapter_name, study_date) VALUES (%s, %s, %s)",
        (subject, "カスタム", today),
    )
    session_id = cur.lastrowid

    for p in problems:
        cur.execute(
            "INSERT INTO custom_session_problems (session_id, chapter_name, problem_number) VALUES (%s, %s, %s)",
            (session_id, p["chapter_name"], p["problem_number"]),
        )
    conn.commit()
    cur.close()
    conn.close()

    return jsonify({"redirect": url_for("study_custom", subject=subject, session_id=session_id)})


@app.route("/<subject>/study_custom/<int:session_id>")
def study_custom(subject, session_id):
    """カスタムセッション学習ページ"""
    if subject not in SUBJECTS:
        return redirect(url_for("home"))

    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT * FROM sessions WHERE id = %s", (session_id,))
    session = cur.fetchone()

    if not session:
        flash("セッションが見つかりません。", "error")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    if session["finished_at"]:
        flash("このセッションは既に終了しています。", "info")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    cur.execute(
        "SELECT chapter_name, problem_number FROM custom_session_problems WHERE session_id = %s ORDER BY chapter_name, problem_number",
        (session_id,),
    )
    custom_problems = cur.fetchall()

    if not custom_problems:
        flash("カスタムセッションの問題が見つかりません。", "error")
        cur.close()
        conn.close()
        return redirect(url_for("index", subject=subject))

    problem_list = [{"chapter_name": r["chapter_name"], "problem_number": r["problem_number"]} for r in custom_problems]

    session_records = {}
    cur.execute(
        "SELECT chapter_name, problem_number, result FROM records WHERE session_id = %s",
        (session_id,),
    )
    for row in cur.fetchall():
        key = f"{row['chapter_name']}_{row['problem_number']}"
        session_records[key] = row["result"]

    problem_stats = {}
    for p in problem_list:
        ch = p["chapter_name"]
        pn = p["problem_number"]
        key = f"{ch}_{pn}"
        cur.execute("""
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN r.result = 'correct' THEN 1 ELSE 0 END) as correct
            FROM records r
            JOIN sessions s ON r.session_id = s.id
            WHERE s.finished_at IS NOT NULL AND r.chapter_name = %s AND r.problem_number = %s
        """, (ch, pn))
        row = cur.fetchone()

        total = row["total"]
        correct = row["correct"] or 0
        accuracy = round(100.0 * correct / total, 1) if total > 0 else None

        problem_stats[key] = {
            "total": total,
            "correct": correct,
            "accuracy": accuracy,
            "session_result": session_records.get(key),
        }
    cur.close()
    conn.close()

    answered = len(session_records)
    total_problems = len(problem_list)

    return render_template(
        "study_custom.html",
        subject=subject,
        session_id=session_id,
        problem_list=problem_list,
        problem_stats=problem_stats,
        study_date=session["study_date"],
        answered=answered,
        total_problems=total_problems,
    )


@app.route("/<subject>/dashboard")
def dashboard(subject):
    """ダッシュボード - 科目別統計一覧"""
    if subject not in SUBJECTS:
        flash("無効な科目です。", "error")
        return redirect(url_for("home"))

    chapter_filter = request.args.get("chapter", "")
    max_accuracy = request.args.get("max_accuracy", "")

    max_acc_val = None
    if max_accuracy:
        try:
            max_acc_val = float(max_accuracy)
        except ValueError:
            pass

    stats = get_stats(
        subject=subject,
        chapter_name=chapter_filter if chapter_filter else None,
        max_accuracy=max_acc_val,
    )

    problems = load_problems_from_excel(subject)
    chapters = list(problems.keys())

    return render_template(
        "dashboard.html",
        subject=subject,
        stats=stats,
        chapters=chapters,
        selected_chapter=chapter_filter,
        max_accuracy=max_accuracy,
    )


@app.route("/<subject>/history")
def history(subject):
    """科目別学習履歴"""
    if subject not in SUBJECTS:
        flash("無効な科目です。", "error")
        return redirect(url_for("home"))

    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT r.chapter_name, r.problem_number, r.result, r.study_date, r.created_at,
               r.session_id
        FROM records r
        JOIN sessions s ON r.session_id = s.id
        WHERE s.finished_at IS NOT NULL AND s.subject = %s
        ORDER BY r.created_at DESC
        LIMIT 200
    """, (subject,))
    rows = cur.fetchall()
    cur.close()
    conn.close()

    records = [dict(r) for r in rows]
    return render_template("history.html", subject=subject, records=records)


@app.route("/<subject>/upload", methods=["GET", "POST"])
def upload(subject):
    """科目別Excelファイルのアップロード"""
    if subject not in SUBJECTS:
        flash("無効な科目です。", "error")
        return redirect(url_for("home"))

    if request.method == "POST":
        file = request.files.get("file")
        if file and file.filename.endswith((".xlsx", ".xls")):
            os.makedirs(DATA_DIR, exist_ok=True)
            file.save(get_excel_path(subject))
            flash(f"{subject}のExcelファイルをアップロードしました。", "success")
        else:
            flash("有効なExcelファイル(.xlsx)を選択してください。", "error")
        return redirect(url_for("upload", subject=subject))

    problems = load_problems_from_excel(subject)
    total_problems = sum(len(v) for v in problems.values())
    return render_template(
        "upload.html",
        subject=subject,
        problems=problems,
        total_problems=total_problems,
    )


init_db()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True, use_reloader=False)
