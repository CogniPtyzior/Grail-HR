// TypeScript model for the analysis JSON edited by consultants.
export type Confidence = 'low' | 'medium' | 'high';
export type SeniorityLevel = 'junior' | 'confirmed' | 'senior' | 'lead' | 'manager' | 'expert' | 'unknown';

export interface CvAnalysis {
  candidate: {
    full_name: string;
    email: string;
    phone: string;
    location: string;
    linkedin_url: string;
    portfolio_url: string;
  };
  summary: {
    profile_title: string;
    short_summary: string;
    long_summary: string;
  };
  career_targeting: {
    primary_job: {
      label: string;
      normalized_label: string;
      confidence: Confidence;
      evidence: string;
    };
    associated_jobs: Array<Record<string, unknown>>;
  };
  seniority: {
    level: SeniorityLevel;
    years_experience_estimate: number | null;
    confidence: Confidence;
    evidence: string;
  };
  experiences: Array<Record<string, unknown>>;
  education: Array<Record<string, unknown>>;
  skills: Array<Record<string, unknown>>;
  tools: Array<Record<string, unknown>>;
  know_how: Array<Record<string, unknown>>;
  soft_skills: Array<Record<string, unknown>>;
  languages: Array<Record<string, unknown>>;
  interests: Array<Record<string, unknown>>;
  warnings: Array<Record<string, unknown>>;
}
