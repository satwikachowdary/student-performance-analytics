import sys
import json

def calculate_scores(inputs):
    # GAD-2 (Anxiety)
    # 0: Not at all, 1: Several days, 2: More than half, 3: Nearly every day
    # Sum range: 0-6
    gad_sum = inputs.get('gad1', 0) + inputs.get('gad2', 0)
    
    # PHQ-2 (Depression)
    # Sum range: 0-6
    phq_sum = inputs.get('phq1', 0) + inputs.get('phq2', 0)
    
    # Map 0-6 scale to 1-10 scale
    # Formula: 1 + (Sum / 6) * 9
    anxiety_score = 1 + (gad_sum / 6.0) * 9
    depression_score = 1 + (phq_sum / 6.0) * 9
    
    return {
        "anxiety_score": int(round(anxiety_score)),
        "depression_score": int(round(depression_score))
    }

if __name__ == "__main__":
    try:
        if len(sys.argv) > 1:
            # Parse JSON input from command line argument
            data = json.loads(sys.argv[1])
            result = calculate_scores(data)
            print(json.dumps(result))
        else:
            print(json.dumps({"error": "No input provided"}))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
