// Unit tests for front validation. Backend remains authoritative, but users receive immediate feedback.
import { describe, expect, it } from 'vitest';
import { cvAnalysisSchema } from '../utils/analysisSchema';

function validAnalysis() {
  return {
    candidate: { full_name: '', email: '', phone: '', location: '', linkedin_url: '', portfolio_url: '' },
    summary: { profile_title: '', short_summary: '', long_summary: '' },
    career_targeting: {
      primary_job: { label: '', normalized_label: '', confidence: 'low', evidence: '' },
      associated_jobs: [],
    },
    seniority: { level: 'unknown', years_experience_estimate: null, confidence: 'low', evidence: '' },
    experiences: [],
    education: [],
    skills: [],
    tools: [],
    know_how: [],
    soft_skills: [],
    languages: [],
    interests: [],
    warnings: [],
  };
}

describe('cvAnalysisSchema', () => {
  it('accepts the empty V1 analysis shape', () => {
    expect(cvAnalysisSchema.safeParse(validAnalysis()).success).toBe(true);
  });

  it('rejects invalid confidence values', () => {
    const analysis = validAnalysis();
    analysis.career_targeting.primary_job.confidence = 'certain';
    expect(cvAnalysisSchema.safeParse(analysis).success).toBe(false);
  });
});
