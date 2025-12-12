import csv
import random

def generate_performance_data():
    # Schema matches the popular "Student Performance Prediction" dataset + Gender + Major
    headers = [
        'Student_ID', 
        'Age',
        'Hours Studied', 
        'Study Efficiency',
        'Previous Scores', 
        'Extracurricular Activities', 
        'Extracurricular Intensity',
        'Sleep Hours', 
        'Sleep Quality',
        'Anxiety Score',
        'Depression Score',
        'Performance Index',
        'Gender',
        'Major'
    ]
    
    filename = 'Student_Performance.csv'
    num_students = 4000
    
    majors = ['Computer Science', 'Engineering', 'Nursing', 'Psychology', 'Business', 'Music', 'Art', 'Biology']
    activities = ['Basketball', 'Debate Club', 'Volunteering', 'Music Band', 'Swimming', 'Chess Club', 'Drama Club', 'Robotics']

    with open(filename, 'w', newline='') as f:
        writer = csv.writer(f)
        writer.writerow(headers)
        
        for student_id in range(1, num_students + 1):
            # Demographics
            age = random.randint(18, 26)
            gender = random.choice(['Male', 'Female'])
            major = random.choice(majors)
            
            # Independent variables
            # Bias: CS/Engineering study more
            if major in ['Computer Science', 'Engineering', 'Nursing']:
                hours_studied = random.randint(3, 9)
                study_efficiency = random.randint(5, 9) # Harder majors might have slightly lower max efficiency due to burnout? Or higher? Let's say variable.
            else:
                hours_studied = random.randint(1, 8)
                study_efficiency = random.randint(4, 10)
            
            # Previous GPA (1.5 to 4.0)
            previous_scores = round(random.uniform(2.0, 4.0), 2)
            
            # Extracurriculars
            has_activity = random.choice([True, False])
            if has_activity:
                extracurricular_name = random.choice(activities)
                extracurricular_intensity = random.randint(1, 10)
            else:
                extracurricular_name = 'None'
                extracurricular_intensity = 0
            
            # Sleep Bias
            # Nursing/CS/Engineering tend to sleep less
            if major in ['Nursing', 'Computer Science', 'Engineering']:
                sleep_hours = random.randint(3, 8) 
            elif major in ['Music', 'Art']:
                sleep_hours = random.randint(5, 10)
            else:
                sleep_hours = random.randint(4, 9)
                
            # Gender bias in sleep (Subtle): Research suggests differences, we'll add random noise
            # Just keeping it mostly major-based for now.
            
            # Sleep Quality (1-10)
            base_quality = int(sleep_hours * 1.1 + random.gauss(0, 1.5))
            if major in ['Music', 'Art', 'Psychology']:
                base_quality += 1 # Slightly better quality
            sleep_quality = min(10, max(1, base_quality))
            
            # Mental Health biases
            # High stress majors
            base_anxiety = random.randint(1, 5)
            if major in ['Nursing', 'Computer Science']:
                base_anxiety += 2
            
            # Sleep deprivation penalty
            sleep_penalty = max(0, (7 - sleep_hours) * 1.5)
            anxiety_score = min(10, max(1, int(base_anxiety + sleep_penalty + random.gauss(0, 1))))
            
            base_depression = random.randint(1, 5)
            dep_penalty = max(0, (7 - sleep_hours) * 1.2)
            depression_score = min(10, max(1, int(base_depression + dep_penalty + random.gauss(0, 1))))
            
            # Performance Index (GPA) Calculation
            # Target range: 1.0 - 4.0
            # Base of ~1.0
            base_score = 0.5
            
            # Factors scaled down significantly
            score = base_score + \
                    (0.05 * hours_studied) + \
                    (0.03 * study_efficiency) + \
                    (0.6 * previous_scores) + \
                    (0.01 * sleep_hours) + \
                    (0.005 * sleep_quality) + \
                    (0.01 * extracurricular_intensity)
            
            if sleep_hours < 5:
                score -= 0.3
                
            score += random.uniform(-0.2, 0.2)
            
            # Major difficulty
            if major in ['Computer Science', 'Engineering', 'Nursing']:
                score -= 0.1
            
            performance_index = max(0.0, min(4.0, round(score, 2)))
            
            writer.writerow([
                student_id,
                age,
                hours_studied,
                study_efficiency,
                previous_scores,
                extracurricular_name,
                extracurricular_intensity,
                sleep_hours,
                sleep_quality,
                anxiety_score,
                depression_score,
                performance_index,
                gender,
                major
            ])
            
    print(f"Data prepared: {num_students} records with detailed Extracurriculars in {filename}")

if __name__ == "__main__":
    generate_performance_data()
