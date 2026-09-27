# Simple Resume Analyzer

skills_required = [
    "python",
    "machine learning",
    "sql",
    "data analysis",
    "deep learning",
    "tensorflow",
    "pandas",
    "numpy",
    "git"
]

print("Paste your resume text below:")
resume = input("\nResume: ").lower()

matched_skills = []

for skill in skills_required:
    if skill in resume:
        matched_skills.append(skill)

score = (len(matched_skills) / len(skills_required)) * 100

print("\n----- Resume Analysis -----")
print(f"Score: {score:.2f}%")

print("\nSkills Found:")
for skill in matched_skills:
    print("✓", skill)

missing_skills = [skill for skill in skills_required if skill not in matched_skills]

print("\nSkills Missing:")
for skill in missing_skills:
    print("✗", skill)

if score >= 80:
    print("\nExcellent Resume!")
elif score >= 50:
    print("\nGood Resume. Add more relevant skills.")
else:
    print("\nResume needs improvement.")