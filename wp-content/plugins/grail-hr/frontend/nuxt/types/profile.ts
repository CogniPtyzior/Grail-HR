// Profile DTOs returned by the WordPress REST API.
import type { CvAnalysis } from './analysis';

export interface ProfileListItem {
  id: number;
  title: string;
  profile_title: string;
  candidate_full_name: string;
  summary_short: string;
  candidate_email: string;
  candidate_phone: string;
  candidate_location: string;
  primary_job_label: string;
  primary_job_confidence: string;
  seniority_level: string;
  seniority_years_estimate: number | null;
  review_status: string;
  analysis_status: string;
  warnings_count: number;
  created_by: { id: number; display_name: string };
  created_at: string;
  updated_at: string;
}

export interface ProfileDetail {
  id: number;
  title: string;
  analysis: CvAnalysis;
  review_status: string;
  analysis_status: string;
  permissions: Record<string, boolean>;
}

export interface PaginatedProfiles {
  data: ProfileListItem[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}
