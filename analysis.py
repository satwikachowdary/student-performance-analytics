import sqlite3
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler

def analyze_data():
    # Connect to DB
    conn = sqlite3.connect('sleepstudy.sqlite')
    
    # Load data
    query = "SELECT * FROM student_performance"
    try:
        df = pd.read_sql_query(query, conn)
    except Exception as e:
        print(f"Error reading DB: {e}")
        return

    if df.empty:
        print("No data found in student_performance")
        return

    # Drop ID for analysis
    df_analysis = df.drop(columns=['id', 'student_id'])
    
    # Encode categorical
    df_encoded = df_analysis.copy()
    # Drop text columns that can't be correlated easily, or keep them if encoded.
    # 'extracurricular_activities' (Name) is text. 'extracurricular_intensity' is numeric.
    # We'll drop the name from correlation matrix input.
    if 'extracurricular_activities' in df_encoded.columns:
        df_encoded = df_encoded.drop(columns=['extracurricular_activities'])
        
    df_encoded['gender'] = df_encoded['gender'].map({'Male': 1, 'Female': 0})
    # Major is also categorical, we can drop it for simple numeric correlation or dummy encode.
    if 'major' in df_encoded.columns:
        df_encoded = df_encoded.drop(columns=['major'])
    
    # 1. Correlation Matrix
    plt.figure(figsize=(10, 8))
    # Select numeric columns only (now including extracurricular_intensity)
    numeric_cols = df_encoded.select_dtypes(include=['float64', 'int64'])
    corr = numeric_cols.corr()
    sns.heatmap(corr, annot=True, cmap='coolwarm', fmt=".2f")
    plt.title('Correlation Matrix')
    plt.tight_layout()
    plt.savefig('correlation_matrix.png')
    plt.close()
    
    # 2. REMOVED Sleep vs Performance (Efficiency/Grades) Graph as requested.

    # 3. Gender Analysis (Boxplot)
    plt.figure(figsize=(8, 6))
    sns.boxplot(x='gender', y='performance_index', data=df, palette="Set2")
    plt.title('GPA Distribution by Gender')
    plt.ylabel('GPA (0.0 - 4.0)')
    plt.savefig('gender_analysis.png')
    plt.close()
    
    # 4. Major Analysis (Bar Chart)
    plt.figure(figsize=(12, 6))
    avg_perf_major = df.groupby('major')['performance_index'].mean().sort_values(ascending=False).reset_index()
    sns.barplot(x='performance_index', y='major', data=avg_perf_major, palette='viridis')
    plt.title('Average GPA by Major')
    plt.xlabel('Average GPA')
    plt.tight_layout()
    plt.savefig('perf_by_major.png')
    plt.close()
    
    # 5. Sleep vs GPA by Major (Heatmap or FacetGrid) -> Simplified to Scatter
    plt.figure(figsize=(12, 6))
    sns.barplot(x='major', y='sleep_hours', hue='gender', data=df, errorbar=None, palette='muted')
    plt.title('Average Sleep Scores by Major & Gender')
    plt.xticks(rotation=45)
    plt.ylabel('Avg Sleep Hours')
    plt.tight_layout()
    plt.savefig('sleep_by_major_gender.png')
    plt.close()

    # 6. Clustering
    scaler = StandardScaler()
    scaled_features = scaler.fit_transform(numeric_cols) # Use numeric only for KMeans
    kmeans = KMeans(n_clusters=3, random_state=42)
    df['Cluster'] = kmeans.fit_predict(scaled_features)
    
    plt.figure(figsize=(10, 6))
    sns.scatterplot(data=df, x='sleep_hours', y='performance_index', hue='Cluster', palette='viridis', alpha=0.6)
    plt.title('Student Clusters')
    plt.savefig('clusters.png')
    plt.close()
    
    # 7. Sleep vs Health (Redesigned)
    plt.figure(figsize=(10, 6))
    df_melted = df.melt(id_vars=['sleep_hours'], value_vars=['anxiety_score', 'depression_score'], 
                        var_name='Metric', value_name='Score')
    sns.lineplot(data=df_melted, x='sleep_hours', y='Score', hue='Metric', marker='o', palette=['orange', 'blue'])
    plt.title('Mental Health Impact of Sleep')
    plt.savefig('sleep_vs_health.png')
    plt.close()
    
    # Save text metrics
    avg_perf_by_sleep = df.groupby('sleep_hours')['performance_index'].mean()
    with open('model_metrics.txt', 'w') as f:
        f.write("Average Performance by Sleep Hours:\n")
        f.write(avg_perf_by_sleep.to_string())
        
    print("Analysis complete. Generated 7 visualizations.")

    conn.close()

if __name__ == "__main__":
    analyze_data()
