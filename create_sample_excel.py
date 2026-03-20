"""サンプルのExcelファイルを科目ごとに作成するスクリプト"""
import openpyxl

subjects = {
    "FAR": [
        ("Chapter 1: Financial Statements", 5),
        ("Chapter 2: Cash and Receivables", 4),
        ("Chapter 3: Inventory", 3),
    ],
    "BAR": [
        ("Chapter 1: Cost Accounting", 4),
        ("Chapter 2: Planning and Budgeting", 3),
    ],
    "REG": [
        ("Chapter 1: Individual Taxation", 5),
        ("Chapter 2: Corporate Taxation", 4),
    ],
    "AUD": [
        ("Chapter 1: Audit Reports", 4),
        ("Chapter 2: Audit Evidence", 3),
    ],
}

for subject, chapters in subjects.items():
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "問題一覧"
    ws.append(["チャプター名", "問題番号"])

    for chapter_name, count in chapters:
        for i in range(1, count + 1):
            ws.append([chapter_name, i])

    wb.save(f"data/problems_{subject}.xlsx")
    print(f"作成: data/problems_{subject}.xlsx")

print("完了")
